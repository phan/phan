<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTHasher;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\Parse\ParseVisitor;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * Checks for duplicate/equivalent array keys and case statements, as well as arrays mixing `key => value, with `value,`.
 *
 * @see DollarDollarPlugin for generic plugin documentation.
 */
class DuplicateArrayKeyPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return DuplicateArrayKeyVisitor::class;
    }
}

/**
 * This class has visitArray called on all array literals in files to check for potential problems with keys.
 *
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class DuplicateArrayKeyVisitor extends PluginAwarePostAnalysisVisitor
{
    private const HASH_PREFIX = "\x00__phan_dnu_";

    // Do not define the visit() method unless a plugin has code and needs to visit most/all node types.

    /**
     * @param Node $node
     * A switch statement's case statement(AST_SWITCH_LIST) node to analyze
     * @override
     */
    public function visitSwitchList(Node $node): void
    {
        $children = $node->children;
        if (count($children) <= 1) {
            // This plugin will never emit errors if there are 0 or 1 elements.
            return;
        }

        $case_constant_set = [];
        $values_to_check = [];
        foreach ($children as $i => $case_node) {
            if (!$case_node instanceof Node) {
                throw new AssertionError("Switch list must contain nodes");
            }
            $case_cond = $case_node->children['cond'];
            if ($case_cond === null) {
                continue;  // This is `default:`. php --syntax-check already checks for duplicates.
            }
            // Skip array entries without literal keys. (Do it before resolving the key value)
            if (!is_scalar($case_cond)) {
                $original_case_cond = $case_cond;
                $case_cond = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $case_cond)->asSingleScalarValueOrNullOrSelf();
                if (is_object($case_cond)) {
                    $case_cond = $original_case_cond;
                }
            }
            if (is_string($case_cond)) {
                $cond_key = "s$case_cond";
                $values_to_check[$i] = $case_cond;
            } elseif (is_int($case_cond)) {
                $cond_key = $case_cond;
                $values_to_check[$i] = $case_cond;
            } elseif (is_bool($case_cond)) {
                $cond_key = $case_cond ? "T" : "F";
                $values_to_check[$i] = $case_cond;
            } else {
                // could be literal null?
                $cond_key = ASTHasher::hash($case_cond);
                if (!is_object($case_cond)) {
                    $values_to_check[$i] = $case_cond;
                }
            }
            if (isset($case_constant_set[$cond_key])) {
                $normalized_case_cond = is_object($case_cond) ? ASTReverter::toShortString($case_cond) : self::normalizeSwitchKey($case_cond);
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($case_node->lineno),
                    'PhanPluginDuplicateSwitchCase',
                    "Duplicate/Equivalent switch case({STRING_LITERAL}) detected in switch statement - the later entry will be ignored in favor of case {CODE} at line {LINE}.",
                    [$normalized_case_cond, ASTReverter::toShortString($case_constant_set[$cond_key]->children['cond']), $case_constant_set[$cond_key]->lineno],
                    Issue::SEVERITY_NORMAL,
                    Issue::REMEDIATION_A,
                    15071
                );
                // Add a fake value to indicate loose equality checks are redundant
                $values_to_check[-1] = true;
            }
            $case_constant_set[$cond_key] = $case_node;
        }
        if (!isset($values_to_check[-1]) && count($values_to_check) > 1 && !self::areAllSwitchCasesTheSameType($values_to_check)) {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument array keys are integers for switch
            $this->extendedLooseEqualityCheck($values_to_check, $children);
        }
    }

    /**
     * @param array<mixed,mixed> $values_to_check scalar constant values of case statements
     */
    private static function areAllSwitchCasesTheSameType(array $values_to_check): bool
    {
        $categories = 0;
        foreach ($values_to_check as $value) {
            if (is_int($value)) {
                $categories |= 1;
                if ($categories !== 1) {
                    return false;
                }
            } elseif (is_string($value)) {
                if (is_numeric($value)) {
                    // This includes float-like strings such as `"1e0"`, which adds ambiguity ("1e0" == "1")
                    return false;
                }
                $categories |= 2;
                if ($categories !== 2) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Perform a heuristic check if any element is `==` a previous element.
     *
     * This is intended to perform well for large arrays.
     *
     * TODO: Do a better job for small arrays.
     * @param array<mixed, mixed> $values_to_check
     * @param list<mixed> $children an array of scalars
     */
    private function extendedLooseEqualityCheck(array $values_to_check, array $children): void
    {
        $numeric_set = [];
        $fuzzy_numeric_set = [];
        foreach ($values_to_check as $i => $value) {
            if (is_numeric($value)) {
                if (is_int($value)) {
                    $old_index = $numeric_set[$value] ?? $fuzzy_numeric_set[$value] ?? null;
                    $numeric_set[$value] = $i;
                } else {
                    // For `"1"`, search for `"1foo"`, `"1bar"`, etc.
                    $original_value = $value;
                    $value = is_float($value) ? (string)$value : (string)filter_var($value, FILTER_VALIDATE_FLOAT);
                    $old_index = $numeric_set[$value] ?? null;
                    if ($value === (string)$original_value) {
                        $old_index = $old_index ?? $fuzzy_numeric_set[$value] ?? null;
                        $numeric_set[$value] = $i;
                    } else {
                        $fuzzy_numeric_set[$value] = $i;
                    }
                }
            } else {
                $value = (float)$value;
                // For `"1foo"`, search for `1` but not `"1bar"`
                $old_index = $numeric_set[$value] ?? null;
                // @phan-suppress-next-line PhanTypeMismatchDimAssignment
                $fuzzy_numeric_set[$value] = $i;
            }
            if ($old_index !== null) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($children[$i]->lineno),
                    'PhanPluginDuplicateSwitchCaseLooseEquality',
                    "Switch case({STRING_LITERAL}) is loosely equivalent (==) to an earlier case ({STRING_LITERAL}) in switch statement - the earlier entry may be chosen instead.",
                    [self::normalizeSwitchKey($values_to_check[$i]), self::normalizeSwitchKey($values_to_check[$old_index])],
                    Issue::SEVERITY_NORMAL,
                    Issue::REMEDIATION_A,
                    15072
                );
            }
        }
    }

    /**
     * @param Node $node
     * A match expressions's arms list (AST_MATCH_ARM_LIST) node to analyze
     * @override
     * @suppress PhanPossiblyUndeclaredProperty
     */
    public function visitMatchArmList(Node $node): void
    {
        $children = $node->children;
        if (!$children) {
            // This plugin will never emit errors if there are 0 elements.
            return;
        }

        $arm_expr_constant_set = [];
        foreach ($children as $arm_node) {
            foreach ($arm_node->children['cond']->children ?? [] as $arm_expr_cond) {
                if ($arm_expr_cond === null) {
                    continue;  // This is `default:`. php --syntax-check already checks for duplicates.
                }
                $lineno = $arm_expr_cond->lineno ?? $arm_node->lineno;
                // Skip array entries without literal keys. (Do it before resolving the key value)
                if (is_object($arm_expr_cond) && ParseVisitor::isConstExpr($arm_expr_cond, ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION)) {
                    // Only infer the value for values not affected by conditions - that will change after the expressions are analyzed
                    $original_cond = $arm_expr_cond;
                    $arm_expr_cond = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $arm_expr_cond)->asSingleScalarValueOrNullOrSelf();
                    if (is_object($arm_expr_cond)) {
                        $arm_expr_cond = $original_cond;
                    }
                }
                if (is_string($arm_expr_cond)) {
                    $cond_key = "s$arm_expr_cond";
                } elseif (is_int($arm_expr_cond)) {
                    $cond_key = $arm_expr_cond;
                } elseif (is_bool($arm_expr_cond)) {
                    $cond_key = $arm_expr_cond ? "T" : "F";
                } else {
                    // TODO: This seems like it'd be flaky with ast\Node->flags and lineno?
                    $cond_key = ASTHasher::hash($arm_expr_cond);
                }
                if (isset($arm_expr_constant_set[$cond_key])) {
                    $normalized_arm_expr_cond = ASTReverter::toShortString($arm_expr_cond);
                    $this->emitPluginIssue(
                        $this->code_base,
                        (clone $this->context)->withLineNumberStart($lineno),
                        'PhanPluginDuplicateMatchArmExpression',
                        "Duplicate match arm expression({STRING_LITERAL}) detected in match expression - the later entry will be ignored in favor of expression {CODE} at line {LINE}.",
                        [$normalized_arm_expr_cond, ASTReverter::toShortString($arm_expr_constant_set[$cond_key][0]), $arm_expr_constant_set[$cond_key][1]],
                        Issue::SEVERITY_NORMAL,
                        Issue::REMEDIATION_A,
                        15071
                    );
                }
                $arm_expr_constant_set[$cond_key] = [$arm_expr_cond, $arm_node->lineno];
            }
        }
    }

    /**
     * @param Node $node
     * An array literal(AST_ARRAY) node to analyze
     * @override
     */
    public function visitArray(Node $node): void
    {
        $children = $node->children;
        if (count($children) <= 1) {
            // This plugin will never emit errors if there are 0 or 1 elements.
            return;
        }

        $has_entry_without_key = false;
        $key_set = [];
        foreach ($children as $entry) {
            if (!($entry instanceof Node)) {
                continue;  // Triggered by code such as `list(, $a) = $expr`. In php 7.1, the array and list() syntax was unified.
            }
            $key = $entry->children['key'] ?? null;
            // Skip array entries without literal keys. (Do it before resolving the key value)
            if (is_null($key)) {
                $has_entry_without_key = true;
                continue;
            }
            if (is_object($key)) {
                $key = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key)->asSingleScalarValueOrNullOrSelf();
                if (is_object($key)) {
                    $key = self::HASH_PREFIX . ASTHasher::hash($entry->children['key']);
                }
            }

            if (isset($key_set[$key])) {
                // @phan-suppress-next-line PhanTypeMismatchDimFetchNullable
                $this->warnAboutDuplicateArrayKey($entry, $key, $key_set[$key]);
            }
            // @phan-suppress-next-line PhanTypeMismatchDimAssignment
            $key_set[$key] = $entry;
        }
        if ($has_entry_without_key && count($key_set) > 0) {
            // This is probably a typo in most codebases. (e.g. ['foo' => 'bar', 'baz'])
            // In phan, InternalFunctionSignatureMap.php does this deliberately with the first parameter being the return type.
            $this->emit(
                'PhanPluginMixedKeyNoKey',
                "Should not mix array entries of the form [key => value,] with entries of the form [value,].",
                [],
                Issue::SEVERITY_NORMAL,
                Issue::REMEDIATION_A,
                15071
            );
        }
    }

    /**
     * @param int|string|float|bool|null $key
     */
    private function warnAboutDuplicateArrayKey(Node $entry, $key, Node $old_entry): void
    {
        if (is_string($key) && strncmp($key, self::HASH_PREFIX, strlen(self::HASH_PREFIX)) === 0) {
            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($entry->lineno),
                'PhanPluginDuplicateArrayKeyExpression',
                "Duplicate dynamic array key expression ({CODE}) detected in array - the earlier entry at line {LINE} will be ignored if the expression had the same value.",
                [ASTReverter::toShortString($entry->children['key']), $old_entry->lineno],
                Issue::SEVERITY_NORMAL,
                Issue::REMEDIATION_A,
                15071
            );
            return;
        }
        $normalized_key = self::normalizeKey($key);
        $this->emitPluginIssue(
            $this->code_base,
            (clone $this->context)->withLineNumberStart($entry->lineno),
            'PhanPluginDuplicateArrayKey',
            "Duplicate/Equivalent array key value({STRING_LITERAL}) detected in array - the earlier entry {CODE} at line {LINE} will be ignored.",
            [$normalized_key, ASTReverter::toShortString($old_entry->children['key']), $old_entry->lineno],
            Issue::SEVERITY_NORMAL,
            Issue::REMEDIATION_A,
            15071
        );
    }

    /**
     * Converts a key to the value it would be if used as a case.
     * E.g. 0, 0.5, and "0" all become the same value(0) when used as an array key.
     *
     * @param int|string|float|bool|null $key - The array key literal to be normalized.
     * @return string - The normalized representation.
     */
    private static function normalizeSwitchKey($key): string
    {
        if (is_int($key)) {
            return (string)$key;
        } elseif (!is_string($key)) {
            return (string)json_encode($key);
        }
        $tmp = [$key => true];
        return ASTReverter::toShortString(key($tmp));
    }

    /**
     * Converts a key to the value it would be if used as an array key.
     * E.g. 0, 0.5, and "0" all become the same value(0) when used as an array key.
     *
     * @param int|string|float|bool|null $key - The array key literal to be normalized.
     * @return string - The normalized representation.
     */
    private static function normalizeKey($key): string
    {
        if (is_int($key)) {
            return (string)$key;
        }
        $tmp = [$key => true];
        return ASTReverter::toShortString(key($tmp));
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new DuplicateArrayKeyPlugin();
