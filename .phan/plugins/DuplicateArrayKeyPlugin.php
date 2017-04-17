<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\PluginIssue;
use ast\Node;

/**
 * Checks for duplicate/equivalent array keys, as well as arrays mixing `key => value, with `value,`.
 *
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class DuplicateArrayKeyPlugin extends AnalysisVisitor implements PostOrderAnalyzer {
    use PluginIssue;

    /**
     * Default visitor that does nothing
     *
     * @param Node $node
     * A node to analyze
     *
     * @return void
     */
    public function visit(Node $node) {
    }

    /**
     * @param Node $node
     * A node to analyze
     *
     * @return void
     */
    public function visitArray(Node $node) {
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
            // Skip array entries without literal keys.
            if ($key === null) {
                $hasEntryWithoutKey = true;
                continue;
            }
            if (!is_scalar($key)) {
                // Skip non-literal keys. (TODO: Could check for constants (e.g. A::B) being used twice)
                continue;
            }
            if (isset($keySet[$key])) {
                $normalizedKey = self::normalizeKey($key);
                $this->emitPluginIssue(
                    $this->code_base,
                    $this->context,
                    'PhanPluginDuplicateArrayKey',
                    "Duplicate/Equivalent array key literal($normalizedKey) detected in array - the earlier entry will be ignored.",
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
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginMixedKeyNoKey',
                "Should not mix array entries of the form [key => value,] with entries of the form [value,].",
                Issue::SEVERITY_NORMAL,
                Issue::REMEDIATION_A,
                15071
            );
        }
    }

    /**
     * Converts a key to the value it would be if used as an array key.
     * E.g. 0, 0.5, and "0" all become the same value(0) when used as an array key.
     *
     * @param int|string|float $key - The array key literal to be normalized.
     * @return string - The normalized representation.
     */
    private static function normalizeKey($key) : string {
        $tmp = [$key => true];
        return var_export(key($tmp), true);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return DuplicateArrayKeyPlugin::class;
