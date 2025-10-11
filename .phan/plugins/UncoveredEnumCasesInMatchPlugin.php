<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\EnumCase;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for match statements that do not cover all cases of an enum.
 *
 * When a match statement's condition is an enum type and all match arm conditions
 * are enum cases from that enum, this plugin warns when:
 * - Not all enum cases are covered
 * - There is no default arm
 *
 * This is especially useful because uncovered enum cases in match expressions
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
 * This visitor analyzes match expressions to detect uncovered enum cases.
 *
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 */
final class UncoveredEnumCasesInMatchVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * Visit a match expression and check for uncovered enum cases
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

        // Get all enum classes from the condition's union type
        $enum_classes = $this->getEnumClassesFromUnionType($cond_type);

        if (empty($enum_classes)) {
            // No enum types in the condition
            return;
        }

        // Check if there's a default arm and collect covered cases
        $has_default = false;
        $covered_cases = [];
        $all_arms_are_enum_cases = true;
        $has_any_arm = false;

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

            // Collect all enum cases covered by this arm
            foreach ($cond_list->children as $arm_cond) {
                $enum_case = $this->getEnumCaseFromExpression($arm_cond);
                if ($enum_case !== null) {
                    $covered_cases[$enum_case] = true;
                } else {
                    // This arm condition is not an enum case
                    $all_arms_are_enum_cases = false;
                }
            }
        }

        // Don't check empty match expressions
        if (!$has_any_arm) {
            return;
        }

        // If there's a default, all cases are effectively covered
        if ($has_default) {
            return;
        }

        // Only warn if all match arms are enum cases from the enum classes in the condition
        if (!$all_arms_are_enum_cases) {
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
        $uncovered_cases = array_diff_key($all_required_cases, $covered_cases);

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
     * Extract enum classes from a union type
     *
     * @return list<Clazz>
     */
    private function getEnumClassesFromUnionType(\Phan\Language\UnionType $union_type): array
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
