<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\EnumCase;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for non-exhaustive match expressions that could throw
 * UnhandledMatchError at runtime.
 *
 * It detects:
 * 1. Enum types where not all cases are covered (and no default arm)
 * 2. Bool types where not both true and false are covered (and no default arm)
 * 3. Non-finite types (string, int, float, etc.) without a default arm
 *
 * This is especially useful because uncovered cases in match expressions
 * will throw UnhandledMatchError at runtime.
 *
 * A plugin file must:
 * - Contain a class that inherits from \Phan\PluginV3
 * - End by returning an instance of that class
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class UncoveredEnumCasesInMatchPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @return string - The name of the visitor that will be called
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return UncoveredEnumCasesInMatchVisitor::class;
    }
}

/**
 * This visitor analyzes match expressions to detect non-exhaustive matches.
 *
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 */
final class UncoveredEnumCasesInMatchVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * Visit a match expression and check for non-exhaustive matches
     *
     * @param Node $node a node of kind AST_MATCH
     */
    public function visitMatch(Node $node): void
    {
        $cond_node = $node->children['cond'];
        $stmts_node = $node->children['stmts'];

        if (!($stmts_node instanceof Node)) {
            return;
        }

        // Get the union type of the match condition
        $cond_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $cond_node
        );

        // Parse all match arms
        $arm_info = $this->parseMatchArms($stmts_node);

        // Don't check empty match expressions
        if (!$arm_info['has_any_arm']) {
            return;
        }

        // If there's a default, all cases are effectively covered
        if ($arm_info['has_default']) {
            return;
        }

        // If any arm has a non-constant condition, skip checking to avoid false positives
        if (!$arm_info['all_arms_constant']) {
            return;
        }

        // Check enum exhaustiveness
        $this->checkEnumExhaustiveness($node, $cond_type, $arm_info);

        // Check bool exhaustiveness (pass cond_node to detect literal booleans)
        $this->checkBoolExhaustiveness($node, $cond_node, $cond_type, $arm_info);

        // Check non-finite types need default
        $this->checkNonFiniteTypeNeedsDefault($node, $cond_type, $arm_info);
    }

    /**
     * Parse match arms to extract information about covered cases
     *
     * @return array{has_default: bool, has_any_arm: bool, all_arms_constant: bool, covered_enum_cases: array<string, true>, covered_bool_values: array<string, true>, has_literal_arms: bool}
     */
    private function parseMatchArms(Node $stmts_node): array
    {
        $has_default = false;
        $has_any_arm = false;
        $all_arms_constant = true;
        $covered_enum_cases = [];
        $covered_bool_values = [];
        $has_literal_arms = false;

        foreach ($stmts_node->children as $arm_node) {
            if (!($arm_node instanceof Node)) {
                continue;
            }

            $cond_list = $arm_node->children['cond'] ?? null;
            if ($cond_list === null) {
                // This is a default arm
                $has_default = true;
                $has_any_arm = true;
                continue;
            }

            if (!($cond_list instanceof Node)) {
                continue;
            }

            $has_any_arm = true;

            // Check each condition in this arm
            foreach ($cond_list->children as $arm_cond) {
                // Check for enum cases
                $enum_case = $this->getEnumCaseFromExpression($arm_cond);
                if ($enum_case !== null) {
                    $covered_enum_cases[$enum_case] = true;
                    continue;
                }

                // Check for bool literals (AST_CONST nodes with name 'true' or 'false')
                $bool_value = self::getBoolLiteralFromExpression($arm_cond);
                if ($bool_value !== null) {
                    $covered_bool_values[$bool_value] = true;
                    $has_literal_arms = true;
                    continue;
                }

                // Check for other scalar literals (int, float, string)
                if (is_int($arm_cond) || is_float($arm_cond) || is_string($arm_cond)) {
                    $has_literal_arms = true;
                    continue;
                }

                // If it's a node that's not an enum case, check if it's a constant expression
                if ($arm_cond instanceof Node) {
                    if (!self::isConstantExpression($arm_cond)) {
                        $all_arms_constant = false;
                    } else {
                        $has_literal_arms = true;
                    }
                }
            }
        }

        return [
            'has_default' => $has_default,
            'has_any_arm' => $has_any_arm,
            'all_arms_constant' => $all_arms_constant,
            'covered_enum_cases' => $covered_enum_cases,
            'covered_bool_values' => $covered_bool_values,
            'has_literal_arms' => $has_literal_arms,
        ];
    }

    /**
     * Check if an expression is a constant (compile-time evaluable)
     */
    private static function isConstantExpression(Node $node): bool
    {
        // Class constants (including enum cases) are constant
        if ($node->kind === \ast\AST_CLASS_CONST) {
            return true;
        }

        // Global constants are constant
        if ($node->kind === \ast\AST_CONST) {
            return true;
        }

        // Variables, function calls, method calls, etc. are not constant
        if ($node->kind === \ast\AST_VAR ||
            $node->kind === \ast\AST_CALL ||
            $node->kind === \ast\AST_METHOD_CALL ||
            $node->kind === \ast\AST_STATIC_CALL ||
            $node->kind === \ast\AST_PROP ||
            $node->kind === \ast\AST_STATIC_PROP) {
            return false;
        }

        // For other node types, assume constant for now
        return true;
    }

    /**
     * Get the bool literal name ('true' or 'false') from an expression, if it is one
     *
     * @return ?string 'true' or 'false' if this is a bool literal, null otherwise
     */
    private static function getBoolLiteralFromExpression(mixed $expr): ?string
    {
        if (!($expr instanceof Node)) {
            return null;
        }

        // Check for AST_CONST (e.g., true, false, null, or other constants)
        if ($expr->kind !== \ast\AST_CONST) {
            return null;
        }

        $name_node = $expr->children['name'] ?? null;
        if (!($name_node instanceof Node)) {
            return null;
        }

        $name = $name_node->children['name'] ?? null;
        if ($name === 'true' || $name === 'false') {
            return $name;
        }

        return null;
    }

    /**
     * Check for missing enum cases
     *
     * @param array{has_default: bool, has_any_arm: bool, all_arms_constant: bool, covered_enum_cases: array<string, true>, covered_bool_values: array<string, true>, has_literal_arms: bool} $arm_info
     */
    private function checkEnumExhaustiveness(Node $node, UnionType $cond_type, array $arm_info): void
    {
        $enum_classes = $this->getEnumClassesFromUnionType($cond_type);

        if (empty($enum_classes)) {
            return;
        }

        // Only warn if there are enum cases in the arms (or no other literal arms)
        if (empty($arm_info['covered_enum_cases']) && $arm_info['has_literal_arms']) {
            return;
        }

        // Get all required enum cases from all enum classes
        $all_required_cases = [];
        foreach ($enum_classes as $enum_class) {
            $enum_cases = $this->getEnumCases($enum_class);
            foreach ($enum_cases as $case_name) {
                $fqsen = $enum_class->getFQSEN()->__toString() . '::' . $case_name;
                $all_required_cases[$fqsen] = $case_name;
            }
        }

        // Find uncovered cases
        $uncovered_cases = array_diff_key($all_required_cases, $arm_info['covered_enum_cases']);

        if (!empty($uncovered_cases)) {
            $uncovered_list = implode(', ', array_values($uncovered_cases));
            $enum_names = implode('|', array_map(
                static fn(Clazz $class): string => $class->getFQSEN()->__toString(),
                $enum_classes
            ));

            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                'PhanPluginUncoveredEnumCasesInMatch',
                'Match expression with {STRING_LITERAL} condition does not cover all cases - missing: {STRING_LITERAL}. Either add the missing cases or add a default arm to handle them.',
                [$enum_names, $uncovered_list],
                \Phan\Issue::SEVERITY_NORMAL,
                \Phan\Issue::REMEDIATION_A,
                15090
            );
        }
    }

    /**
     * Check for missing bool values (true/false)
     *
     * @param array{has_default: bool, has_any_arm: bool, all_arms_constant: bool, covered_enum_cases: array<string, true>, covered_bool_values: array<string, true>, has_literal_arms: bool} $arm_info
     */
    private function checkBoolExhaustiveness(Node $node, Node|string|int|float $cond_node, UnionType $cond_type, array $arm_info): void
    {
        // If there are no bool literals in the arms, don't warn (might be using other comparison)
        if (empty($arm_info['covered_bool_values'])) {
            return;
        }

        // If the condition itself is a literal boolean (e.g., match(true) or match(false)),
        // don't warn - the match can only ever receive that literal value and cannot throw
        // UnhandledMatchError for the opposite boolean.
        if (self::getBoolLiteralFromExpression($cond_node) !== null) {
            return;
        }

        // Check if the condition type contains bool-related types
        $has_bool_type = false;
        $has_true_type = false;
        $has_false_type = false;

        foreach ($cond_type->getTypeSet() as $type) {
            if ($type instanceof BoolType && !($type instanceof TrueType) && !($type instanceof FalseType)) {
                $has_bool_type = true;
            } elseif ($type instanceof TrueType) {
                $has_true_type = true;
            } elseif ($type instanceof FalseType) {
                $has_false_type = true;
            }
        }

        // If there's no bool type at all, nothing to check
        if (!$has_bool_type && !$has_true_type && !$has_false_type) {
            return;
        }

        $missing = [];

        // Use Phan's type narrowing to detect what's missing:
        // - If type is `true` (narrowed), it means only `true` was in arms -> `false` is missing
        // - If type is `false` (narrowed), it means only `false` was in arms -> `true` is missing
        // - If type is `bool`, check what's covered

        if ($has_bool_type) {
            // Full bool type - check what's explicitly covered
            if (!isset($arm_info['covered_bool_values']['true'])) {
                $missing[] = 'true';
            }
            if (!isset($arm_info['covered_bool_values']['false'])) {
                $missing[] = 'false';
            }
        } elseif ($has_true_type && !$has_false_type) {
            // Type narrowed to `true` - means only `true` is covered, `false` is missing
            $missing[] = 'false';
        } elseif ($has_false_type && !$has_true_type) {
            // Type narrowed to `false` - means only `false` is covered, `true` is missing
            $missing[] = 'true';
        }
        // If both $has_true_type and $has_false_type, then both are covered

        if (!empty($missing)) {
            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                'PhanPluginNonExhaustiveBoolMatch',
                'Match expression with bool condition does not cover all cases - missing: {STRING_LITERAL}. Either add the missing cases or add a default arm.',
                [implode(', ', $missing)],
                \Phan\Issue::SEVERITY_NORMAL,
                \Phan\Issue::REMEDIATION_A,
                15091
            );
        }
    }

    /**
     * Check if non-finite types (string, int, float, etc.) need a default arm
     *
     * @param array{has_default: bool, has_any_arm: bool, all_arms_constant: bool, covered_enum_cases: array<string, true>, covered_bool_values: array<string, true>, has_literal_arms: bool} $arm_info
     */
    private function checkNonFiniteTypeNeedsDefault(Node $node, UnionType $cond_type, array $arm_info): void
    {
        // If there's no literal arms, skip (enum-only checks are handled separately)
        if (!$arm_info['has_literal_arms']) {
            return;
        }

        // If there are enum cases covered, the enum check will handle it
        if (!empty($arm_info['covered_enum_cases'])) {
            return;
        }

        // If there are bool values covered, the bool check will handle it
        if (!empty($arm_info['covered_bool_values'])) {
            return;
        }

        // Check if any type in the union is non-finite
        $non_finite_types = [];
        foreach ($cond_type->getTypeSet() as $type) {
            $name = $type->getName();
            // Skip null type (can be in a union)
            if ($name === 'null') {
                continue;
            }
            // Skip bool-related types (handled by bool check)
            if ($type instanceof BoolType || $type instanceof TrueType || $type instanceof FalseType) {
                continue;
            }
            // Skip enum types (handled by enum check)
            if ($type->isObjectWithKnownFQSEN()) {
                $fqsen = $type->asFQSEN();
                if ($fqsen instanceof FullyQualifiedClassName && $this->code_base->hasClassWithFQSEN($fqsen)) {
                    $class = $this->code_base->getClassByFQSEN($fqsen);
                    if ($class->isEnum()) {
                        continue;
                    }
                }
            }
            // These types are non-finite
            if (in_array($name, ['string', 'int', 'float', 'array', 'object', 'mixed', 'iterable', 'callable', 'resource'], true)) {
                $non_finite_types[] = $name;
            }
        }

        if (!empty($non_finite_types)) {
            $type_list = implode('|', array_unique($non_finite_types));
            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                'PhanPluginNonExhaustiveMatchNeedsDefault',
                'Match expression with {STRING_LITERAL} condition is non-exhaustive and has no default arm. Add a default arm to handle unexpected values.',
                [$type_list],
                \Phan\Issue::SEVERITY_NORMAL,
                \Phan\Issue::REMEDIATION_A,
                15092
            );
        }
    }

    /**
     * Extract enum classes from a union type
     *
     * @return list<Clazz>
     */
    private function getEnumClassesFromUnionType(UnionType $union_type): array
    {
        $enum_classes = [];

        foreach ($union_type->getTypeSet() as $type) {
            if (!$type->isObjectWithKnownFQSEN()) {
                continue;
            }

            $fqsen = $type->asFQSEN();
            if (!($fqsen instanceof FullyQualifiedClassName)) {
                continue;
            }

            if (!$this->code_base->hasClassWithFQSEN($fqsen)) {
                continue;
            }

            $class = $this->code_base->getClassByFQSEN($fqsen);
            if ($class->isEnum()) {
                $enum_classes[] = $class;
            }
        }

        return $enum_classes;
    }

    /**
     * Get the FQSEN string of an enum case from an expression node
     *
     * @return ?string the FQSEN of the enum case, or null if not an enum case
     */
    private function getEnumCaseFromExpression(mixed $expr): ?string
    {
        if (!($expr instanceof Node)) {
            return null;
        }

        // Check for AST_CLASS_CONST (e.g., Suit::Hearts)
        if ($expr->kind !== \ast\AST_CLASS_CONST) {
            return null;
        }

        $class_node = $expr->children['class'];
        $const_name = $expr->children['const'];

        if (!is_string($const_name)) {
            return null;
        }

        // Try to resolve the class
        $type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $class_node
        );

        foreach ($type->getTypeSet() as $class_type) {
            if (!$class_type->isObjectWithKnownFQSEN()) {
                continue;
            }

            $class_fqsen = $class_type->asFQSEN();
            if (!($class_fqsen instanceof FullyQualifiedClassName)) {
                continue;
            }

            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                continue;
            }

            $class = $this->code_base->getClassByFQSEN($class_fqsen);
            if (!$class->isEnum()) {
                continue;
            }

            // Check if this constant is an enum case
            $const_fqsen = \Phan\Language\FQSEN\FullyQualifiedClassConstantName::make(
                $class_fqsen,
                $const_name
            );

            if (!$this->code_base->hasClassConstantWithFQSEN($const_fqsen)) {
                continue;
            }

            $constant = $this->code_base->getClassConstantByFQSEN($const_fqsen);
            if ($constant instanceof EnumCase) {
                return $const_fqsen->__toString();
            }
        }

        return null;
    }

    /**
     * Get all enum case names from an enum class
     *
     * @return list<string> array of case names
     */
    private function getEnumCases(Clazz $enum_class): array
    {
        $cases = [];

        foreach ($enum_class->getConstantMap($this->code_base) as $constant) {
            if ($constant instanceof EnumCase) {
                $cases[] = $constant->getName();
            }
        }

        return $cases;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UncoveredEnumCasesInMatchPlugin();
