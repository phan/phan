<?php declare(strict_types=1);
namespace Phan\AST;

use ast\Node;

/**
 * This simplifies a PHP AST into a form which is easier to analyze,
 * and returns the new Node.
 * The original \ast\Node objects are not modified.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal
 */
class ASTSimplifier
{
    public function __construct()
    {
    }

    /**
     * @param Node $node
     * @return array<int,Node> (Equivalent list of nodes to [$node], possibly a clone with modifications)
     */
    private function apply(Node $node) : array
    {
        switch ($node->kind) {
            case \ast\AST_FUNC_DECL:
            case \ast\AST_METHOD:
            case \ast\AST_CLOSURE:
            case \ast\AST_CLASS:
            case \ast\AST_DO_WHILE:
            case \ast\AST_FOR:
            case \ast\AST_FOREACH:
            case \ast\AST_WHILE:
                return [$this->applyToStmts($node)];
            //case \ast\AST_BREAK:
            //case \ast\AST_CONTINUE:
            //case \ast\AST_RETURN:
            //case \ast\AST_THROW:
            //case \ast\AST_EXIT:
            default:
                return [$node];
            case \ast\AST_STMT_LIST:
                return [$this->applyToStatementList($node)];
        // Conditional blocks:
            case \ast\AST_IF:
                return $this->normalizeIfStatement($node);
            case \ast\AST_TRY:
                return [$this->normalizeTryStatement($node)];
        }
    }

    /**
     * @param Node $node - A node which will have its child statements simplified.
     * @return Node - The same node, or an equivalent simplified node
     */
    private function applyToStmts(Node $node) : Node
    {
        $stmts = $node->children['stmts'];
        // Can be null, a single statement, or (possibly) a scalar instead of a node?
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
    private function applyToStatementList(Node $statement_list) : Node
    {
        if ($statement_list->kind !== \ast\AST_STMT_LIST) {
            $statement_list = self::buildStatementList($statement_list->lineno ?? 0, $statement_list);
        }
        $new_children = [];
        foreach ($statement_list->children as $child_node) {
            if ($child_node instanceof Node) {
                foreach ($this->apply($child_node) as $new_child_node) {
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
    private static function buildStatementList(int $lineno, Node ...$child_nodes) : Node
    {
        return new Node(
            \ast\AST_STMT_LIST,
            0,
            $child_nodes,
            $lineno
        );
    }

    /**
     * @param array<int,?Node|?float|?int|?string|?float|?bool> $statements
     * @return array{0:array<int,\ast\Node>,1:bool} - [New/old list, bool $modified] An equivalent list after simplifying (or the original list)
     * @suppress PhanPartialTypeMismatchReturn
     */
    private function normalizeStatementList(array $statements) : array
    {
        $modified = false;
        $new_statements = [];
        foreach ($statements as $stmt) {
            $new_statements[] = $stmt;
            if (!($stmt instanceof Node)) {
                continue;
            }
            if ($stmt->kind !== \ast\AST_IF) {
                continue;
            }
            // Run normalizeIfStatement again.
            \array_pop($new_statements);
            \array_push($new_statements, ...$this->normalizeIfStatement($stmt));
            $modified = $modified || \end($new_statements) !== $stmt;
            continue;
        }
        return [$modified ? $new_statements : $statements, $modified];
    }

    /**
     * Replaces the last node in a list with a list of 0 or more nodes
     * @param array<int,Node> $nodes
     * @param \ast\Node ...$new_statements
     * @return void
     */
    private static function replaceLastNodeWithNodeList(array &$nodes, Node... $new_statements)
    {
        \assert(count($nodes) > 0);
        \array_pop($nodes);
        foreach ($new_statements as $stmt) {
            $nodes[] = $stmt;
        }
    }

    const NON_SHORT_CIRCUITING_BINARY_OPERATOR_FLAGS = [
        \ast\flags\BINARY_BOOL_XOR,
        \ast\flags\BINARY_IS_IDENTICAL,
        \ast\flags\BINARY_IS_NOT_IDENTICAL,
        \ast\flags\BINARY_IS_EQUAL,
        \ast\flags\BINARY_IS_NOT_EQUAL,
        \ast\flags\BINARY_IS_SMALLER,
        \ast\flags\BINARY_IS_SMALLER_OR_EQUAL,
        \ast\flags\BINARY_IS_GREATER,
        \ast\flags\BINARY_IS_GREATER_OR_EQUAL,
        \ast\flags\BINARY_SPACESHIP,
    ];

    /**
     * If this returns true, the expression has no side effects, and can safely be reordered.
     * (E.g. returns true for `MY_CONST` or `false` in `if (MY_CONST === ($x = y))`
     *
     * @param Node|string|float|int $node
     */
    private static function isExpressionWithoutSideEffects($node) : bool
    {
        if (!($node instanceof Node)) {
            return true;
        }
        switch ($node->kind) {
            case \ast\AST_CONST:
            case \ast\AST_MAGIC_CONST:
            case \ast\AST_NAME:
                return true;
            case \ast\AST_BINARY_OP:
            case \ast\AST_CLASS_CONST:
                return self::isExpressionWithoutSideEffects($node->children['class']);
            default:
                return false;
        }
    }

    /**
     * Converts an if statement to one which is easier for phan to analyze
     * E.g. repeatedly makes these conversions
     * if (A && B) {X} -> if (A) { if (B) {X}}
     * if ($var = A) {X} -> $var = A; if ($var) {X}
     * @return array<int,\ast\Node> - One or more nodes created from $original_node.
     *        Will return [$original_node] if no modifications were made.
     */
    private function normalizeIfStatement(Node $original_node) : array
    {
        $old_nodes = [];
        $nodes = [$original_node];
        // Repeatedly apply these rules
        while ($old_nodes !== $nodes) {
            $old_nodes = $nodes;
            $node = $nodes[count($nodes) - 1];
            $node->flags = 0;
            $if_cond = $node->children[0]->children['cond'];
            if (!($if_cond instanceof Node)) {
                break;  // No transformation rules apply here.
            }

            if ($if_cond->kind === \ast\AST_UNARY_OP &&
                $if_cond->flags === \ast\flags\UNARY_BOOL_NOT) {
                $cond_node = $if_cond->children['expr'];
                if ($cond_node instanceof Node &&
                        $cond_node->kind === \ast\AST_UNARY_OP &&
                        $cond_node->flags === \ast\flags\UNARY_BOOL_NOT) {
                    self::replaceLastNodeWithNodeList($nodes, $this->applyIfDoubleNegateReduction($node));
                    continue;
                }
                if (\count($node->children) === 1) {
                    self::replaceLastNodeWithNodeList($nodes, $this->applyIfNegatedToIfElseReduction($node));
                    continue;
                }
            }
            if ($if_cond->kind === \ast\AST_BINARY_OP && \in_array($if_cond->flags, self::NON_SHORT_CIRCUITING_BINARY_OPERATOR_FLAGS, true)) {
                // if (($var = A) === B) {X} -> $var = A; if ($var === B) { X}
                $if_cond_children = $if_cond->children;
                if (\in_array($if_cond_children['left']->kind ?? 0, [\ast\AST_ASSIGN, \ast\AST_ASSIGN_REF], true) &&
                        ($if_cond_children['left']->children['var']->kind ?? 0) === \ast\AST_VAR) {
                    self::replaceLastNodeWithNodeList($nodes, ...$this->applyAssignInLeftSideOfBinaryOpReduction($node));
                    continue;
                }
                if (\in_array($if_cond_children['right']->kind ?? 0, [\ast\AST_ASSIGN, \ast\AST_ASSIGN_REF], true) &&
                        ($if_cond_children['right']->children['var']->kind ?? 0) === \ast\AST_VAR &&
                        self::isExpressionWithoutSideEffects($if_cond_children['left'])) {
                    self::replaceLastNodeWithNodeList($nodes, ...$this->applyAssignInRightSideOfBinaryOpReduction($node));
                    continue;
                }
                // TODO: If the left hand side is a constant or class constant or literal, that's safe to rearrange as well
                // (But `foo($y = something()) && $x = $y` is not safe to rearrange)
            }
            if (count($node->children) === 1) {
                if ($if_cond->kind === \ast\AST_BINARY_OP &&
                        $if_cond->flags === \ast\flags\BINARY_BOOL_AND) {
                    self::replaceLastNodeWithNodeList($nodes, $this->applyIfAndReduction($node));
                    // if (A && B) {X} -> if (A) { if (B) {X}}
                    // Do this, unless there is an else statement that can be executed.
                    continue;
                }
            } elseif (count($node->children) === 2) {
                if ($if_cond->kind === \ast\AST_UNARY_OP &&
                        $if_cond->flags === \ast\flags\UNARY_BOOL_NOT &&
                        $node->children[1]->children['cond'] === null) {
                    self::replaceLastNodeWithNodeList($nodes, $this->applyIfNegateReduction($node));
                    continue;
                }
            } elseif (count($node->children) >= 3) {
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
     * if (($var = A) === B) {X} -> $var = A; if ($var === B) { X }
     *
     * @return array{0:Node,1:Node}
     */
    private function applyAssignInLeftSideOfBinaryOpReduction(Node $node) : array
    {
        $inner_assign_statement = $node->children[0]->children['cond']->children['left'];
        \assert($inner_assign_statement instanceof Node);  // already checked
        $inner_assign_var = $inner_assign_statement->children['var'];

        \assert($inner_assign_var->kind === \ast\AST_VAR);

        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond']->children['left'] = $inner_assign_var;
        $new_node_elem->flags = 0;
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno ?? 0;
        $new_node->flags = 0;
        return [$inner_assign_statement, $new_node];
    }

    /**
     * if (B === ($var = A)) {X} -> $var = A; if (B === $var) { X }
     *
     * @return array{0:Node,1:Node}
     */
    private function applyAssignInRightSideOfBinaryOpReduction(Node $node) : array
    {
        $inner_assign_statement = $node->children[0]->children['cond']->children['right'];
        $inner_assign_var = $inner_assign_statement->children['var'];

        \assert($inner_assign_statement instanceof Node);
        \assert($inner_assign_var->kind === \ast\AST_VAR);

        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond']->children['right'] = $inner_assign_var;
        $new_node_elem->flags = 0;
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno ?? 0;
        $new_node->flags = 0;
        return [$inner_assign_statement, $new_node];
    }

    /**
     * Creates a new node with kind \ast\AST_IF from two branches
     */
    private function buildIfNode(Node $l, Node $r) : Node
    {
        \assert($l->kind === \ast\AST_IF_ELEM);
        \assert($r->kind === \ast\AST_IF_ELEM);
        return new Node(
            \ast\AST_IF,
            0,
            [$l, $r],
            $l->lineno ?? 0
        );
    }

    /**
     * maps if (A) {X} elseif (B) {Y} else {Z} -> if (A) {Y} else { if (B) {Y} else {Z}}
     */
    private function applyIfChainReduction(Node $node) : Node
    {
        $children = $node->children;  // Copy of array of Nodes of type IF_ELEM
        if (count($children) <= 2) {
            return $node;
        }
        \assert(\is_array($children));
        while (count($children) > 2) {
            $r = array_pop($children);
            $l = array_pop($children);
            assert($l instanceof Node && $r instanceof Node);
            $l->children['stmts']->flags = 0;
            $r->children['stmts']->flags = 0;
            $inner_if_node = self::buildIfNode($l, $r);
            $new_r = new Node(
                \ast\AST_IF_ELEM,
                0,
                [
                    'cond' => null,
                    'stmts' => self::buildStatementList($inner_if_node->lineno, ...($this->normalizeIfStatement($inner_if_node))),
                ],
                0
            );
            $children[] = $new_r;
        }
        // $children is an array of 2 nodes of type IF_ELEM
        return new Node(\ast\AST_IF, 0, $children, $node->lineno);
    }

    /**
     * Converts if (A && B) {X}` -> `if (A) { if (B){X}}`
     * @return Node simplified node logically equivalent to $node, with kind \ast\AST_IF.
     */
    private function applyIfAndReduction(Node $node) : Node
    {
        \assert(count($node->children) == 1);
        $inner_node_elem = clone($node->children[0]);  // AST_IF_ELEM
        $inner_node_elem->children['cond'] = $inner_node_elem->children['cond']->children['right'];
        $inner_node_elem->flags = 0;
        $inner_node_lineno = $inner_node_elem->lineno ?? 0;

        // Normalize code such as `if (A && (B && C)) {...}` recursively.
        $inner_node_stmts = $this->normalizeIfStatement(new Node(
            \ast\AST_IF,
            0,
            [$inner_node_elem],
            $inner_node_lineno
        ));

        $inner_node_stmt_list = new Node(\ast\AST_STMT_LIST, 0, $inner_node_stmts, $inner_node_lineno);
        $outer_node_elem = clone($node->children[0]);  // AST_IF_ELEM
        $outer_node_elem->children['cond'] = $node->children[0]->children['cond']->children['left'];
        $outer_node_elem->children['stmts'] = $inner_node_stmt_list;
        $outer_node_elem->flags = 0;
        return new Node(
            \ast\AST_IF,
            $node->lineno,
            [$outer_node_elem],
            0
        );
    }

    /**
     * Converts if ($x = A) {Y} -> $x = A; if ($x) {Y}
     * This allows analyzing variables set in if blocks outside of the `if` block
     * @return array{0:Node,1:Node} [$outer_assign_statement, $new_node]
     */
    private function applyIfAssignReduction(Node $node) : array
    {
        $outer_assign_statement = $node->children[0]->children['cond'];
        \assert($outer_assign_statement instanceof Node);
        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond'] = $new_node_elem->children['cond']->children['var'];
        $new_node_elem->flags = 0;
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno ?? 0;
        $new_node->flags = 0;
        return [$outer_assign_statement, $new_node];
    }

    /**
     * Converts if (!x) {Y} else {Z} -> if (x) {Z} else {Y}
     * This improves phan's analysis for cases such as `if (!is_string($x))`.
     */
    private function applyIfNegateReduction(Node $node) : Node
    {
        \assert(count($node->children) === 2);
        \assert($node->children[0]->children['cond']->flags === \ast\flags\UNARY_BOOL_NOT);
        \assert($node->children[1]->children['cond'] === null);
        $new_node = clone($node);
        $new_node->children = [clone($new_node->children[1]), clone($new_node->children[0])];
        $new_node->children[0]->children['cond'] = $node->children[0]->children['cond']->children['expr'];
        $new_node->children[1]->children['cond'] = null;
        $new_node->flags = 0;
        return $new_node;
    }

    /**
     * Converts if (!!(x)) {Y} -> if (x) {Y}
     * This improves phan's analysis for cases such as `if (!!x)`
     */
    private function applyIfDoubleNegateReduction(Node $node) : Node
    {
        \assert($node->children[0]->children['cond']->flags === \ast\flags\UNARY_BOOL_NOT);
        \assert($node->children[0]->children['cond']->children['expr']->flags === \ast\flags\UNARY_BOOL_NOT);

        $new_cond = $node->children[0]->children['cond']->children['expr']->children['expr'];
        $new_node = clone($node);
        $new_node->flags = 0;
        $new_node->children[0] = clone($node->children[0]);
        $new_node->children[0]->flags = 0;
        $new_node->children[0]->children['cond'] = $new_cond;

        return $new_node;
    }

    private function applyIfNegatedToIfElseReduction(Node $node) : Node
    {
        \assert(\count($node->children) === 1);
        $if_elem = $node->children[0];
        \assert($if_elem->children['cond']->flags === \ast\flags\UNARY_BOOL_NOT);
        $lineno = $if_elem->lineno;
        $new_else_elem = new Node(
            \ast\AST_IF_ELEM,
            0,
            [
                'cond' => null,
                'stmts' => $if_elem->children['stmts'],
            ],
            $lineno
        );
        $new_if_elem = new Node(
            \ast\AST_IF_ELEM,
            0,
            [
                'cond' => $if_elem->children['cond']->children['expr'],
                'stmts' => new Node(\ast\AST_STMT_LIST, 0, [], $if_elem->lineno),
            ],
            $lineno
        );
        return new Node(
            \ast\AST_IF,
            0,
            [$new_if_elem, $new_else_elem],
            $node->lineno
        );
    }

    /**
     * Recurses on a list of 0 or more catch statements. (as in try/catch)
     * Returns an equivalent list of catch AST nodes (or the original if no changes were made)
     */
    private function normalizeCatchesList(Node $catches) : Node
    {
        $list = $catches->children;
        $new_list = array_map(function (Node $node) {
            return $this->applyToStmts($node);
        }, $list);
        if ($new_list === $list) {
            return $catches;
        }
        $new_catches = clone($catches);
        $new_catches->children = $new_list;
        $new_catches->flags = 0;
        return $new_catches;
    }

    /**
     * Recurses on a try/catch/finally node, applying simplifications(catch/finally are optional)
     * Returns an equivalent try/catch/finally node (or the original if no changes were made)
     */
    private function normalizeTryStatement(Node $node) : Node
    {
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
        $new_node->flags = 0;
        return $new_node;
    }

    public static function applyStatic(Node $node) : Node
    {
        $rewriter = new self();
        $nodes = $rewriter->apply($node);
        \assert(\count($nodes) === 1);
        return $nodes[0];
    }
}
