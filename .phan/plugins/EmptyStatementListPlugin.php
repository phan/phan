<?php

declare(strict_types=1);

use ast\Node;
use Phan\Config;
use Phan\Library\FileCache;
use Phan\Parse\ParseVisitor;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This file checks for empty statement lists in loops/branches.
 * Due to Phan's AST rewriting for easier analysis, this may miss some edge cases.
 *
 * It hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
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
final class EmptyStatementListPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * If true, then never allow empty statement lists, even if there is a TODO/FIXME/"deliberately empty" comment.
     * @var bool
     * @internal
     */
    public static $ignore_todos = false;

    public function __construct()
    {
        self::$ignore_todos = (bool) (Config::getValue('plugin_config')['empty_statement_list_ignore_todos'] ?? false);
    }

    /**
     * @return string - The name of the visitor that will be called.
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return EmptyStatementListVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
final class EmptyStatementListVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @var list<Node> set by plugin framework
     * @suppress PhanReadOnlyProtectedProperty
     */
    protected $parent_node_list;

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitIf(Node $node): void
    {
        // @phan-suppress-next-line PhanUndeclaredProperty set by ASTSimplifier
        if (isset($node->is_simplified)) {
            $first_child = end($node->children);
            if (!$first_child instanceof Node || $first_child->children['cond'] === null) {
                return;
            }
            $last_if_elem = reset($node->children);
        } else {
            $last_if_elem = end($node->children);
        }
        if (!$last_if_elem instanceof Node) {
            // probably impossible
            return;
        }
        $stmts_node = $last_if_elem->children['stmts'];
        if (!$stmts_node instanceof Node) {
            // probably impossible
            return;
        }
        if ($stmts_node->children) {
            // the last if element has statements
            return;
        }
        if ($last_if_elem->children['cond'] === null) {
            // Don't bother warning about else
            return;
        }
        if ($this->hasTODOComment($stmts_node->lineno, $node)) {
            // Don't warn if there is a FIXME/TODO comment in/around the empty statement list
            return;
        }

        $this->emitPluginIssue(
            $this->code_base,
            (clone($this->context))->withLineNumberStart($last_if_elem->children['stmts']->lineno ?? $last_if_elem->lineno),
            'PhanPluginEmptyStatementIf',
            'Empty statement list statement detected for the last if/elseif statement',
            []
        );
    }

    private function hasTODOComment(int $lineno, Node $analyzed_node, ?int $end_lineno = null): bool
    {
        if (EmptyStatementListPlugin::$ignore_todos) {
            return false;
        }
        $file = FileCache::getOrReadEntry($this->context->getFile());
        $lines = $file->getLines();
        $end_lineno = max($lineno, $end_lineno ?? $this->findEndLine($lineno, $analyzed_node));
        for ($i = $lineno; $i <= $end_lineno; $i++) {
            $line = $lines[$i] ?? null;
            if (!is_string($line)) {
                break;
            }
            if (preg_match('/todo|fixme|deliberately empty/i', $line) > 0) {
                return true;
            }
        }
        return false;
    }

    private function findEndLine(int $lineno, Node $search_node): int
    {
        for ($node_index = count($this->parent_node_list) - 1; $node_index >= 0; $node_index--) {
            $node = $this->parent_node_list[$node_index] ?? null;
            if (!$node) {
                continue;
            }
            if (isset($node->endLineno)) {
                // Return the end line of the function declaration.
                return $node->endLineno;
            }
            if ($node->kind === ast\AST_STMT_LIST) {
                foreach ($node->children as $i => $c) {
                    if ($c === $search_node) {
                        $next_node = $node->children[$i + 1] ?? null;
                        if ($next_node instanceof Node) {
                            return $next_node->lineno - 1;
                        }
                        break;
                    }
                }
            }
            $search_node = $node;
        }
        // Give up and guess.
        return $lineno + 5;
    }

    /**
     * @param Node $node
     * A node of kind ast\AST_FOR to analyze
     * @override
     */
    public function visitFor(Node $node): void
    {
        $stmts_node = $node->children['stmts'];
        if (!$stmts_node instanceof Node) {
            // impossible
            return;
        }
        if ($stmts_node->children || ($node->children['loop']->children ?? null)) {
            // the for loop has statements, in the body and/or in the loop condition.
            return;
        }
        if ($this->hasTODOComment($stmts_node->lineno, $node)) {
            // Don't warn if there is a FIXME/TODO comment in/around the empty statement list
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($stmts_node->lineno ?? $node->lineno),
            'PhanPluginEmptyStatementForLoop',
            'Empty statement list statement detected for the for loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitWhile(Node $node): void
    {
        $stmts_node = $node->children['stmts'];
        if (!$stmts_node instanceof Node) {
            return; // impossible
        }
        if ($stmts_node->children) {
            // the while loop has statements
            return;
        }
        if ($this->hasTODOComment($stmts_node->lineno, $node)) {
            // Don't warn if there is a FIXME/TODO comment in/around the empty statement list
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($stmts_node->lineno ?? $node->lineno),
            'PhanPluginEmptyStatementWhileLoop',
            'Empty statement list statement detected for the while loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitDoWhile(Node $node): void
    {
        $stmts_node = $node->children['stmts'];
        if (!$stmts_node instanceof Node) {
            return; // impossible
        }
        if ($stmts_node->children ?? null) {
            // the while loop has statements
            return;
        }
        if ($this->hasTODOComment($stmts_node->lineno, $node)) {
            // Don't warn if there is a FIXME/TODO comment in/around the empty statement list
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($stmts_node->lineno),
            'PhanPluginEmptyStatementDoWhileLoop',
            'Empty statement list statement detected for the do-while loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitForeach(Node $node): void
    {
        $stmts_node = $node->children['stmts'];
        if (!$stmts_node instanceof Node) {
            // impossible
            return;
        }
        if ($stmts_node->children) {
            // the while loop has statements
            return;
        }
        if ($this->hasTODOComment($stmts_node->lineno, $node)) {
            // Don't warn if there is a FIXME/TODO comment in/around the empty statement list
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($stmts_node->lineno),
            'PhanPluginEmptyStatementForeachLoop',
            'Empty statement list statement detected for the foreach loop',
            []
        );
    }

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitTry(Node $node): void
    {
        ['try' => $try_node, 'finally' => $finally_node] = $node->children;
        if (!$try_node->children) {
            if (!$this->hasTODOComment($try_node->lineno, $node, $node->children['catches']->children[0]->lineno ?? $finally_node->lineno ?? null)) {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($try_node->lineno),
                    'PhanPluginEmptyStatementTryBody',
                    'Empty statement list statement detected for the try statement\'s body',
                    []
                );
            }
        }
        if ($finally_node instanceof Node && !$finally_node->children) {
            if (!$this->hasTODOComment($finally_node->lineno, $node)) {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($finally_node->lineno),
                    'PhanPluginEmptyStatementTryFinally',
                    'Empty statement list statement detected for the try\'s finally body',
                    []
                );
            }
        }
    }

    /**
     * @param Node $node
     * A node of kind ast\AST_SWITCH to analyze
     * @override
     */
    public function visitSwitch(Node $node): void
    {
        // Check all case statements and return if something that isn't a no-op is seen.
        foreach ($node->children['stmts']->children ?? [] as $c) {
            if (!$c instanceof Node) {
                // impossible
                continue;
            }

            $children = $c->children['stmts']->children ?? null;
            if ($children) {
                if (count($children) > 1) {
                    return;
                }
                $only_node = $children[0];
                if ($only_node instanceof Node) {
                    if (!in_array($only_node->kind, [ast\AST_CONTINUE, ast\AST_BREAK], true)) {
                        return;
                    }
                    if (($only_node->children['depth'] ?? 1) !== 1) {
                        // not a no-op
                        return;
                    }
                }
            }
            if (!ParseVisitor::isConstExpr($c->children['cond'])) {
                return;
            }
        }
        $this->emitPluginIssue(
            $this->code_base,
            clone($this->context)->withLineNumberStart($node->lineno),
            'PhanPluginEmptyStatementSwitch',
            'No side effects seen for any cases of this switch statement',
            []
        );
    }
}
// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new EmptyStatementListPlugin();
