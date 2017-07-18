<?php declare(strict_types=1);
namespace Phan\AST;

use Phan\Analysis\BlockExitStatusChecker;
use ast\Node;

/**
 * This simplifies a PHP AST into a form which is easier to analyze,
 * and returns the new \ast\Node.
 * The original \ast\Node objects are not modified.
 */
class ASTSimplifier {
    /** @var string - for debugging purposes */
    private $_filename;

    public function __construct(string $filename = 'unknown') {
        $this->_filename = $filename;
    }

    /**
     * @param Node $node
     * @return Node[] (Equivalent list of nodes to [$node], possibly a clone with modifications)
     */
    private function apply(Node $node) : array {
        switch ($node->kind) {
        case \ast\AST_FUNC_DECL:
        case \ast\AST_METHOD:
        case \ast\AST_CLOSURE:
        case \ast\AST_CLASS:
            return [$this->applyToStmts($node)];
        case \ast\AST_BREAK:
        case \ast\AST_CONTINUE:
        case \ast\AST_RETURN:
        case \ast\AST_THROW:
        case \ast\AST_EXIT:
            return [$node];
        case \ast\AST_STMT_LIST:
            return [$this->applyToStatementList($node)];
        // Conditional blocks:
        case \ast\AST_DO_WHILE:
        case \ast\AST_FOR:
        case \ast\AST_FOREACH:
        case \ast\AST_WHILE:
            return [$this->applyToStmts($node)];
        case \ast\AST_IF:
            return $this->normalizeIfStatement($node);
        case \ast\AST_TRY:
            return [$this->normalizeTryStatement($node)];
        }

        // TODO add more types, recurse into switch/case statements, etc.
        return [$node];
    }

    /**
     * @param Node $node - A node which will have its child statements simplified.
     * @return Node - The same node, or an equivalent simplified node
     */
    private function applyToStmts(Node $node) : Node {
        $stmts = $node->children['stmts'];
        // Can be null, a single statement, or (possibly) a scalar instead of a node?
        // TODO: newer versions of php-ast may guarantee Node?
        if (!($stmts instanceof Node)) {
            return $node;
        }
        $new_stmts = $this->applyToStatementList($stmts);
        if ($new_stmts === $stmts) {
            return $node;
        }
        $new_node = clone($node);
        $new_node->children['stmts'] = $new_stmts;
        return $new_node;
    }

    /**
     * @param Node $statement_list - The statement list to simplify
     * @return Node - an equivalent statement list (Identical, or a clone)
     */
    private function applyToStatementList(Node $statement_list) : Node {
        if ($statement_list->kind !== \ast\AST_STMT_LIST) {
            // TODO: This check may be unnecessary in new php-ast versions
            $statement_list = self::buildStatementList($statement_list->lineno ?? 0, $statement_list);
        }
        $new_children = [];
        foreach ($statement_list->children as $child_node) {
            if ($child_node instanceof Node) {
                foreach ($this->apply($child_node) as $new_child_node) {
                    // The apply() step can also modify the nodes, check below with ===
                    $new_children[] = $new_child_node;
                }
            } else {
                $new_children[] = $child_node;
            }
        }
        list($new_children, $modified) = $this->normalizeStatementList($new_children);
        if (!$modified && $new_children === $statement_list->children) {
            return $statement_list;
        }
        $clone_node = clone($statement_list);
        $clone_node->children = $new_children;
        return $clone_node;
    }

    /**
     * Creates a new node with kind \ast\AST_STMT_LIST from a list of 0 or more child nodes.
     */
    private static function buildStatementList(int $lineno, Node ...$child_nodes) : Node {
        $stmt_list = new Node();
        $stmt_list->lineno = $lineno;
        $stmt_list->kind = \ast\AST_STMT_LIST;
        $stmt_list->flags = 0;
        $stmt_list->children = $child_nodes;
        return $stmt_list;
    }

    /**
     * Get a modifiable Node that is a clone of the statement or statement list.
     * The resulting Node has kind AST_STMT_LIST.
     */
    private static function cloneStatementList(Node $stmt_list = null) : Node {
        if (\is_null($stmt_list)) {
            return self::buildStatementList(0);
        }
        if ($stmt_list->kind === \ast\AST_STMT_LIST) {
            return clone($stmt_list);
        }
        // $parent->children['stmts'] is a statement, not a statement list.
        return self::buildStatementList($stmt_list->lineno ?? 0, $stmt_list);
    }

    /**
     * @param \ast\Node[] $statements
     * @return \ast\Node[][]|bool[] - [New/old list, bool $modified] An equivalent list after simplifying (or the original list)
     */
    private function normalizeStatementList(array $statements) : array {
        $modified = false;
        for ($i = \count($statements) - 1; $i >= 0; $i--) {
            $stmt = $statements[$i];
            if (!($stmt instanceof Node)) {
                continue;
            }
            if ($stmt->kind !== \ast\AST_IF) {
                continue;
            }
            if (\count($statements) > $i + 1) {
                $N = \count($stmt->children);
                if ($N > 2) {
                    continue;  // early exit, no simplification rules apply
                }
                // if (A) {X} else {Y_NOEXIT} Z -> if (A) {X; Z} else {Y_NOEXIT}
                // (Note that the above rule does not apply to elseifs)
                if ($N === 2 &&
                        ($stmt->children[1]->children['stmts'] instanceof Node) &&
                        $stmt->children[1]->children['cond'] === null &&  // cannot be elseif
                        BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($stmt->children[1]->children['stmts'])) {
                    // If the else statement is guaranteed to break/continue/return/throw,
                    // then merge the remaining statements following that into the `if` block.
                    $new_if_elem = clone($stmt->children[0]);
                    $new_stmts = self::cloneStatementList($new_if_elem->children['stmts']);
                    $new_stmts->children = array_merge($new_stmts->children, array_slice($statements, $i + 1));
                    $new_if_elem->children['stmts'] = $new_stmts;
                    $new_if = clone($stmt);
                    $new_if->children[0] = $new_if_elem;
                    // Replace the old `if` node (followed by statements) with the new `if` node
                    while (\count($statements) > $i) {
                        \array_pop($statements);
                    }
                    $statements[$i] = $new_if;
                    $modified = true;
                    continue;
                }
                if (($N == 1 || ($N == 2 && $stmt->children[1]->children['cond'] === null)) &&
                        $stmt->children[0]->children['stmts'] instanceof Node &&  // Why does php-ast sometime return string.
                        BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($stmt->children[0]->children['stmts'])) {
                    // If the if statement is guaranteed to break/continue/return/throw,
                    // then merge the remaining statements following that into the `else` block (not `elseif`)
                    // Create an `else` block if necessary.
                    // This prevents inferences(e.g. in Phan) from the `if` block from leaking out into the remaining statemtns.
                    if ($N == 1) {
                        $new_else_elem = clone($stmt->children[0]);
                        $new_else_elem->children['cond'] = null;
                        // Don't clone the original if statement - It might not be a statement list.
                        $new_else_elem->children['stmts'] = self::buildStatementList($stmt->children[0]->lineno ?? 0);
                    } else {
                        \assert($N === 2);
                        $new_else_elem = clone($stmt->children[1]);
                        // Convert a singular statement (or null) into a statement list, if necessary.
                        $new_else_elem->children['stmts'] = self::cloneStatementList($new_else_elem->children['stmts']);
                    }
                    $new_else_elem->children['stmts']->children = array_merge($new_else_elem->children['stmts']->children, array_slice($statements, $i + 1));
                    $new_if_else = clone($stmt);
                    $new_if_else->children[1] = $new_else_elem;
                    // We might end up undoing a negation as well, now that there is an else branch.
                    // Run normalizeIfStatement again.
                    while (\count($statements) > $i) {
                        \array_pop($statements);
                    }
                    \array_push($statements, ...$this->normalizeIfStatement($new_if_else));
                    $modified = true;
                    continue;
                }
            }
        }
        return [$statements, $modified];
    }

    /**
     * Replaces the last node in a list with a list of 0 or more nodes
     * @param \ast\Node[] $nodes
     * @param \ast\Node ...$new_statements
     * @return void
     */
    private static function replaceLastNodeWithNodeList(array &$nodes, Node... $new_statements) {
        \assert(count($nodes) > 0);
        array_pop($nodes);
        foreach ($new_statements as $stmt) {
            $nodes[] = $stmt;
        }
    }

    /**
     * Converts an if statement to one which is easier for phan to analyze
     * E.g. repeatedly makes these conversions
     * if (A && B) {X} -> if (A) { if (B) {X}}
     * if ($var = A) {X} -> $var = A; if ($var) {X}
     * @return \ast\Node[] - One or more nodes created from $original_node.
     *        Will return [$original_node] if no modifications were made.
     */
    private function normalizeIfStatement(Node $original_node) : array {
        $old_nodes = [];
        $nodes = [$original_node];
        // Repeatedly apply these rules
        while ($old_nodes !== $nodes) {
            $old_nodes = $nodes;
            $node = $nodes[count($nodes) - 1];
            $if_cond = $node->children[0]->children['cond'];
            if (!($if_cond instanceof Node)) {
                break;  // No transformation rules apply here.
            }

            if ($if_cond->kind === \ast\AST_UNARY_OP &&
                    $if_cond->flags === \ast\flags\UNARY_BOOL_NOT &&
                    $if_cond->children['expr']->kind === \ast\AST_UNARY_OP &&
                    $if_cond->children['expr']->flags === \ast\flags\UNARY_BOOL_NOT) {
                self::replaceLastNodeWithNodeList($nodes, $this->_applyIfDoubleNegateReduction($node));
                continue;
            }
            if (count($node->children) === 1) {
                if ($if_cond->kind === \ast\AST_BINARY_OP &&
                        $if_cond->flags === \ast\flags\BINARY_BOOL_AND) {
                    self::replaceLastNodeWithNodeList($nodes, $this->applyIfAndReduction($node));
                    // if (A && B) {X} -> if (A) { if (B) {X}}
                    // Do this, unless there is an else statement that can be executed.
                    continue;
                }
            } else if (count($node->children) === 2) {
                if ($if_cond->kind === \ast\AST_UNARY_OP &&
                        $if_cond->flags === \ast\flags\UNARY_BOOL_NOT &&
                        $node->children[1]->children['cond'] === null) {
                    self::replaceLastNodeWithNodeList($nodes, $this->applyIfNegateReduction($node));
                    continue;
                }
            } else if (count($node->children) >= 3) {
                self::replaceLastNodeWithNodeList($nodes, $this->applyIfChainReduction($node));
                continue;
            }
            if ($if_cond->kind === \ast\AST_ASSIGN &&
                    $if_cond->children['var']->kind === \ast\AST_VAR) {
                // if ($var = A) {X} -> $var = A; if ($var) {X}
                // do this whether or not there is an else.
                // TODO: Could also reduce `if (($var = A) && B) {X} else if (C) {Y} -> $var = A; ....
                self::replaceLastNodeWithNodeList($nodes, ...$this->applyIfAssignReduction($node));
                continue;
            }
        }
        return $nodes;
    }

    /**
     * Creates a new node with kind \ast\AST_IF from two branches
     */
    private function buildIfNode(Node $l, Node $r) : Node {
        \assert($l->kind === \ast\AST_IF_ELEM);
        \assert($r->kind === \ast\AST_IF_ELEM);
        $if_node = new Node();
        $if_node->kind = \ast\AST_IF;
        $if_node->lineno = $l->lineno ?? 0;
        $if_node->flags = 0;
        $if_node->children = [$l, $r];
        return $if_node;
    }

    /**
     * maps if (A) {X} elseif (B) {Y} else {Z} -> if (A) {Y} else { if (B) {Y} else {Z}}
     */
    private function applyIfChainReduction(Node $node) : Node {
        $children = $node->children;  // Copy of array of Nodes of type IF_ELEM
        if (count($children) <= 2) {
            return $node;
        }
        \assert(\is_array($children));
        while (count($children) > 2) {
            $r = array_pop($children);
            $l = array_pop($children);
            $inner_if_node = self::buildIfNode($l, $r);
            $new_r = new Node();
            $new_r->kind = \ast\AST_IF_ELEM;
            $new_r->lineno = $l->lineno ?? 0;
            $new_r->flags = 0;
            $new_r->children = [
                'cond' => null,
                'stmts' => self::buildStatementList($inner_if_node->lineno, ...($this->normalizeIfStatement($inner_if_node))),
            ];

            $children[] = $new_r;
        }
        // $children is an array of 2 nodes of type IF_ELEM
        $new_node = clone($node);
        $new_node->children = $children;
        return $new_node;
    }

    /**
     * Converts if (A && B) {X}` -> `if (A) { if (B){X}}`
     * @return Node simplified node logically equivalent to $node, with kind \ast\AST_IF.
     */
    private function applyIfAndReduction(Node $node) : Node {
        \assert(count($node->children) == 1);
        $inner_node_elem = clone($node->children[0]);  // AST_IF_ELEM
        $inner_node_elem->children['cond'] = $inner_node_elem->children['cond']->children['right'];
        $inner_node = clone($node);  // AST_IF
        $inner_node->children[0] = $inner_node_elem;
        $inner_node->lineno = $inner_node_elem->lineno ?? 0;
        $inner_node_stmt_list = self::buildStatementList($inner_node->lineno, $inner_node);  // AST_STMT_LIST
        $outer_node_elem = clone($node->children[0]);  // AST_IF_ELEM
        $outer_node_elem->children['cond'] = $node->children[0]->children['cond']->children['left'];
        $outer_node_elem->children['stmts'] = $inner_node_stmt_list;
        $outer_node = clone($node);  // AST_IF
        $outer_node->children[0] = $outer_node_elem;
        return $outer_node;
    }

    /**
     * Converts if ($x = A) {Y} -> $x = A; if ($x) {Y}
     * This allows analyzing variables set in if blocks outside of the `if` block
     * @return \ast\Node[] [$outer_assign_statement, $new_node]
     */
    private function applyIfAssignReduction(Node $node) : array {
        $outer_assign_statement = $node->children[0]->children['cond'];
        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond'] = $new_node_elem->children['cond']->children['var'];
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno ?? 0;
        return [$outer_assign_statement, $new_node];
    }

    /**
     * Converts if (!x) {Y} else {Z} -> if (x) {Z} else {Y}
     * This improves phan's analysis for cases such as `if (!is_string($x))`.
     */
    private function applyIfNegateReduction(Node $node) : Node {
        \assert(count($node->children) === 2);
        \assert($node->children[0]->children['cond']->flags === \ast\flags\UNARY_BOOL_NOT);
        \assert($node->children[1]->children['cond'] === null);
        $new_node = clone($node);
        $new_node->children = [clone($new_node->children[1]), clone($new_node->children[0])];
        $new_node->children[0]->children['cond'] = $node->children[0]->children['cond']->children['expr'];
        $new_node->children[1]->children['cond'] = null;
        return $new_node;
    }

    /**
     * Converts if (!!(x)) {Y} -> if (x) {Y}
     * This improves phan's analysis for cases such as `if (!!x)`
     */
    private function _applyIfDoubleNegateReduction(Node $node) : Node {
        \assert($node->children[0]->children['cond']->flags === \ast\flags\UNARY_BOOL_NOT);
        \assert($node->children[0]->children['cond']->children['expr']->flags === \ast\flags\UNARY_BOOL_NOT);

        $new_cond = $node->children[0]->children['cond']->children['expr']->children['expr'];
        $new_node = clone($node);
        $new_node->children[0] = clone($node->children[0]);
        $new_node->children[0]->children['cond'] = $new_cond;

        return $new_node;
    }

    /**
     * Recurses on a list of 0 or more catch statements. (as in try/catch)
     * Returns an equivalent list of catch AST nodes (or the original if no changes were made)
     */
    private function normalizeCatchesList(Node $catches) : Node {
        $list = $catches->children;
        $new_list = array_map(function(Node $node) {
            return $this->applyToStmts($node);
        }, $list);
        if ($new_list === $list) {
            return $catches;
        }
        $new_catches = clone($catches);
        $new_catches->children = $new_list;
        return $new_catches;
    }

    /**
     * Recurses on a try/catch/finally node, applying simplifications(catch/finally are optional)
     * Returns an equivalent try/catch/finally node (or the original if no changes were made)
     */
    private function normalizeTryStatement(Node $node) : Node {
        $try = $node->children['try'];
        $catches = $node->children['catches'];
        $finally = $node->children['finally'] ?? null;
        $new_try = $this->applyToStatementList($try);
        $new_catches = $catches ? $this->normalizeCatchesList($catches) : $catches;
        $new_finally = $finally ? $this->applyToStatementList($finally) : $finally;
        if ($new_try === $try && $new_catches === $catches && $new_finally === $finally) {
            return $node;
        }
        $new_node = clone($node);
        $new_node->children['try'] = $new_try;
        $new_node->children['catches'] = $new_catches;
        $new_node->children['finally'] = $new_finally;
        return $new_node;
    }

    public static function applyStatic(Node $node, string $filename = 'unknown') : Node {
        $rewriter = new self($filename);
        $nodes = $rewriter->apply($node);
        \assert(\count($nodes) === 1);
        return $nodes[0];
    }
}
