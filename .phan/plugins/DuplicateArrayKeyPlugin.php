<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use ast\Node;

/**
 * Checks for duplicate/equivalent array keys and case statements, as well as arrays mixing `key => value, with `value,`.
 *
 * @see DollarDollarPlugin for generic plugin documentation.
 */
class DuplicateArrayKeyPlugin extends PluginV2 implements AnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwareAnalysisVisitor subclass
     * @override
     */
    public static function getAnalyzeNodeVisitorClassName() : string
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
class DuplicateArrayKeyVisitor extends PluginAwareAnalysisVisitor
{
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
            $case_cond = $this->tryToResolveKey($case_cond);

            if (!is_scalar($case_cond)) {
                // Skip non-literal keys.
                continue;
            }
            if (isset($case_constant_set[$case_cond])) {
                $normalized_case_cond = self::normalizeKey($case_cond);
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
            $case_constant_set[$case_cond] = true;
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

        $hasEntryWithoutKey = false;
        $keySet = [];
        foreach ($children as $entry) {
            if ($entry === null) {
                continue;  // Triggered by code such as `list(, $a) = $expr`. In php 7.1, the array and list() syntax was unified.
            }
            $key = $entry->children['key'];
            // Skip array entries without literal keys. (Do it before resolving the key value)
            if ($key === null) {
                $hasEntryWithoutKey = true;
                continue;
            }
            $key = $this->tryToResolveKey($key);

            if (!is_scalar($key)) {
                // Skip non-literal keys.
                continue;
            }
            if (isset($keySet[$key])) {
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
            $keySet[$key] = true;
        }
        if ($hasEntryWithoutKey && count($keySet) > 0) {
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
     * @param int|string|float|Node $key
     * @return int|string|float|Node|array - If possible, converted to a scalar.
     */
    private function tryToResolveKey($key)
    {
        if (!($key instanceof ast\Node)) {
            return $key;
        }
        $kind = $key->kind;
        if (!in_array($kind, [\ast\AST_CLASS_CONST, \ast\AST_CONST, \ast\AST_MAGIC_CONST], true)) {
            return $key;
        }
        // if key is constant, take it in account
        $context_node = new ContextNode($this->code_base, $this->context, $key);
        try {
            if ($kind === \ast\AST_CLASS_CONST) {
                $key = $context_node->getClassConst()->getNodeForValue();
            } elseif ($kind === \ast\AST_CONST) {
                $key = $context_node->getConst()->getNodeForValue();
            } else {
                $key = $context_node->getValueForMagicConst();
            }
            if ($key === null) {
                $key = '';
            }
        } catch (IssueException $e) {
            // This is redundant, but do it anyway
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $e->getIssueInstance()
            );
        } catch (NodeException $e) {
            // E.g. Can't figure out constant class in node
            // (ignore)
        }
        return $key;
    }

    /**
     * Converts a key to the value it would be if used as an array key.
     * E.g. 0, 0.5, and "0" all become the same value(0) when used as an array key.
     *
     * @param int|string|float $key - The array key literal to be normalized.
     * @return string - The normalized representation.
     */
    private static function normalizeKey($key) : string
    {
        $tmp = [$key => true];
        return var_export(key($tmp), true);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new DuplicateArrayKeyPlugin();
