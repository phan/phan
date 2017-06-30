<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\LegacyAnalyzeNodeCapability;
use ast\Node;

/**
 * Checks for duplicate/equivalent array keys, as well as arrays mixing `key => value, with `value,`.
 *
 * @see DollarDollarPlugin for generic plugin documentation.
 */
class DuplicateArrayKeyPlugin extends PluginV2 implements LegacyAnalyzeNodeCapability {

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node
     * The php-ast Node being analyzed.
     *
     * @param Node $node
     * The parent node of the given node (if one exists).
     *
     * @return void
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        (new DuplicateArrayKeyVisitor($code_base, $context, $this))(
            $node
        );
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
class DuplicateArrayKeyVisitor extends AnalysisVisitor {

    /** @var PluginV2 */
    private $plugin;

    public function __construct(
        CodeBase $code_base,
        Context $context,
        PluginV2 $plugin
    ) {
        // After constructing on parent, `$code_base` and
        // `$context` will be available as protected properties
        // `$this->code_base` and `$this->context`.
        parent::__construct($code_base, $context);

        // We take the plugin so that we can call
        // `$this->plugin->emitIssue(...)` on it to emit issues
        // to the user.
        $this->plugin = $plugin;
    }

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
            \assert(is_scalar($key));  // redundant Phan annotation.
            if (isset($keySet[$key])) {
                $normalizedKey = self::normalizeKey($key);
                $this->plugin->emitIssue(
                    $this->code_base,
                    $this->context,
                    'PhanPluginDuplicateArrayKey',
                    "Duplicate/Equivalent array key literal({VARIABLE}) detected in array - the earlier entry will be ignored.",
                    [(string)$normalizedKey],
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
            $this->plugin->emitIssue(
                $this->code_base,
                $this->context,
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
return new DuplicateArrayKeyPlugin();
