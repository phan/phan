<?php

declare(strict_types=1);

use ast\Node;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for duplicate constant declarations within a statement list.
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * DuplicateConstantPlugin hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class DuplicateConstantPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return DuplicateConstantVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class DuplicateConstantVisitor extends PluginAwarePostAnalysisVisitor
{

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @param Node $node
     * A node to analyze of kind ast\AST_STMT_LIST
     * @override
     */
    public function visitStmtList(Node $node): void
    {
        if (count($node->children) <= 1) {
            return;
        }
        $declarations = [];

        foreach ($node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($child->kind === ast\AST_CONST_DECL) {
                foreach ($child->children as $const) {
                    if (!$const instanceof Node) {
                        continue;
                    }
                    $name = (string) $const->children['name'];
                    if (isset($declarations[$name])) {
                        $this->warnDuplicateConstant($name, $declarations[$name], $const);
                    } else {
                        $declarations[$name] = $const;
                    }
                }
            } elseif ($child->kind === ast\AST_CALL) {
                $expr = $child->children['expr'];
                if ($expr instanceof Node && $expr->kind === ast\AST_NAME && strcasecmp((string) $expr->children['name'], 'define') === 0) {
                    $name = $child->children['args']->children[0] ?? null;
                    if (is_string($name)) {
                        if (isset($declarations[$name])) {
                            $this->warnDuplicateConstant($name, $declarations[$name], $expr);
                        } else {
                            $declarations[$name] = $expr;
                        }
                    }
                }
            }
        }
    }

    private function warnDuplicateConstant(string $name, Node $original_def, Node $new_def): void
    {
        $this->emitPluginIssue(
            $this->code_base,
            (clone $this->context)->withLineNumberStart($new_def->lineno),
            'PhanPluginDuplicateConstant',
            'Constant {CONST} was previously declared at line {LINE} - the previous declaration will be used instead',
            [$name, $original_def->lineno]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new DuplicateConstantPlugin();
