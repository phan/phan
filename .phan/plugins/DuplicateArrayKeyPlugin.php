<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTHasher;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\PluginV2;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;

/**
 * Checks for duplicate/equivalent array keys and case statements, as well as arrays mixing `key => value, with `value,`.
 *
 * @see DollarDollarPlugin for generic plugin documentation.
 */
class DuplicateArrayKeyPlugin extends PluginV2 implements PostAnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
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
    const HASH_PREFIX = "\x00__phan_dnu_";

    // Do not define the visit() method unless a plugin has code and needs to visit most/all node types.

    /**
     * @param Node $node
     * A switch statement's case statement(AST_SWITCH_LIST) node to analyze
     *
     * @return void
     *
     * @override
     */
    public function visitSwitchList(Node $node)
    {
        $children = $node->children;
        if (count($children) <= 1) {
            // This plugin will never emit errors if there are 0 or 1 elements.
            return;
        }

        $case_constant_set = [];
        foreach ($children as $case_node) {
            $case_cond = $case_node->children['cond'];
            if ($case_cond === null) {
                continue;  // This is `default:`. php --syntax-check already checks for duplicates.
            }
            // Skip array entries without literal keys. (Do it before resolving the key value)
            if (!is_scalar($case_cond)) {
                $case_cond = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $case_cond)->asSingleScalarValueOrNullOrSelf();
                if (is_object($case_cond)) {
                    // Skip non-literal keys.
                    continue;
                }
            }
            if (is_string($case_cond)) {
                $cond_key = "s$case_cond";
            } elseif (is_int($case_cond)) {
                $cond_key = $case_cond;
            } else {
                $cond_key = json_encode($case_cond);
            }
            if (isset($case_constant_set[$cond_key])) {
                $normalized_case_cond = self::normalizeSwitchKey($case_cond);
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($case_node->lineno),
                    'PhanPluginDuplicateSwitchCase',
                    "Duplicate/Equivalent switch case({STRING_LITERAL}) detected in switch statement - the later entry will be ignored.",
                    [(string)$normalized_case_cond],
                    Issue::SEVERITY_NORMAL,
                    Issue::REMEDIATION_A,
                    15071
                );
            }
            $case_constant_set[$cond_key] = $case_node->lineno;
        }
    }

    /**
     * @param Node $node
     * An array literal(AST_ARRAY) node to analyze
     *
     * @return void
     *
     * @override
     */
    public function visitArray(Node $node)
    {
        $children = $node->children;
        if (count($children) <= 1) {
            // This plugin will never emit errors if there are 0 or 1 elements.
            return;
        }

        $has_entry_without_key = false;
        $key_set = [];
        foreach ($children as $entry) {
            if ($entry === null) {
                continue;  // Triggered by code such as `list(, $a) = $expr`. In php 7.1, the array and list() syntax was unified.
            }
            $key = $entry->children['key'];
            // Skip array entries without literal keys. (Do it before resolving the key value)
            if ($key === null) {
                $has_entry_without_key = true;
                continue;
            }
            if (!is_scalar($key)) {
                $key = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key)->asSingleScalarValueOrNullOrSelf();
                if (is_object($key)) {
                    $key = self::HASH_PREFIX . ASTHasher::hash($entry->children['key']);
                }
            }

            if (isset($key_set[$key])) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                $this->warnAboutDuplicateArrayKey($node, $entry, $key);
            }
            // @phan-suppress-next-line PhanTypeMismatchDimAssignment
            $key_set[$key] = true;
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
    private function warnAboutDuplicateArrayKey(Node $node, Node $entry, $key)
    {
        if (is_string($key) && strncmp($key, self::HASH_PREFIX, strlen(self::HASH_PREFIX)) === 0) {
            $this->emitPluginIssue(
                $this->code_base,
                clone($this->context)->withLineNumberStart($entry->lineno ?? $node->lineno),
                'PhanPluginDuplicateArrayKeyExpression',
                "Duplicate dynamic array key expression ({CODE}) detected in array - the earlier entry will be ignored if the expression had the same value.",
                [ASTReverter::toShortString($entry->children['key'])],
                Issue::SEVERITY_NORMAL,
                Issue::REMEDIATION_A,
                15071
            );
            return;
        }
        $normalized_key = self::normalizeKey($key);
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($entry->lineno ?? $node->lineno),
            'PhanPluginDuplicateArrayKey',
            "Duplicate/Equivalent array key value({STRING_LITERAL}) detected in array - the earlier entry will be ignored.",
            [(string)$normalized_key],
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
    private static function normalizeSwitchKey($key) : string
    {
        if (is_int($key)) {
            return (string)$key;
        } elseif (!is_string($key)) {
            return (string)json_encode($key);
        }
        $tmp = [$key => true];
        return var_export(key($tmp), true);
    }

    /**
     * Converts a key to the value it would be if used as an array key.
     * E.g. 0, 0.5, and "0" all become the same value(0) when used as an array key.
     *
     * @param int|string|float|bool|null $key - The array key literal to be normalized.
     * @return string - The normalized representation.
     */
    private static function normalizeKey($key)
    {
        if (is_int($key)) {
            return (string)$key;
        }
        $tmp = [$key => true];
        return var_export(key($tmp), true);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new DuplicateArrayKeyPlugin();
