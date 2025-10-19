<?php

declare(strict_types=1);

namespace Phan\AST;

use AssertionError;
use ast;
use ast\flags;
use ast\Node;

use Phan\Config;
use function array_fill;
use function array_map;
use function array_merge;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function intdiv;
use function ltrim;
use function max;
use function min;
use function preg_match;
use function preg_split;
use function substr;
use function trim;

/**
 * This simplifies a PHP AST into a form which is easier to analyze,
 * and returns the new Node.
 * The original ast\Node objects are not modified.
 *
 * @phan-file-suppress PhanPossiblyUndeclaredProperty
 */
class ASTSimplifier
{
    private const SYNTHETIC_DOC_COMMENT_VAR = '/** @var array<array-key,mixed> */';
    private const SYNTHETIC_DOC_COMMENT_RETURN = '/** @return array<array-key,mixed> */';

    public function __construct()
    {
    }

    /**
     * @return non-empty-list<Node> (Equivalent list of nodes to [$node], possibly a clone with modifications)
     */
    private static function apply(Node $node): array
    {
        switch ($node->kind) {
            case ast\AST_FUNC_DECL:
            case ast\AST_METHOD:
            case ast\AST_CLOSURE:
            case ast\AST_CLASS:
            case ast\AST_DO_WHILE:
            case ast\AST_FOREACH:
                return [self::applyToStmts($node)];
            case ast\AST_FOR:
                return self::normalizeForStatement($node);
            case ast\AST_WHILE:
                return self::normalizeWhileStatement($node);
            case ast\AST_PROP_GROUP:
                return [self::applyToPropertyGroup($node)];
            case ast\AST_ASSIGN:
                return [self::applyToAssignment($node)];
            case ast\AST_STATIC:
                return [self::applyToStatic($node)];
            case ast\AST_RETURN:
                return [self::applyToReturn($node)];
            case ast\AST_ARRAY:
                [$trimmed_array] = self::trimArrayNode($node, self::getTrimMaxTotalElements());
                return [$trimmed_array];
            //case ast\AST_BREAK:
            //case ast\AST_CONTINUE:
            //case ast\AST_RETURN:
            //case ast\AST_THROW:
            //case ast\AST_EXIT:
            default:
                [$trimmed_node] = self::trimDescendantArrays($node);
                return [$trimmed_node];
            case ast\AST_STMT_LIST:
                return [self::applyToStatementList($node)];
        // Conditional blocks:
            case ast\AST_IF:
                return self::normalizeIfStatement($node);
            case ast\AST_TRY:
                return [self::normalizeTryStatement($node)];
        }
    }

    /**
     * @param Node $node - A node which will have its child statements simplified.
     * @return Node - The same node, or an equivalent simplified node
     */
    private static function applyToStmts(Node $node): Node
    {
        $stmts = $node->children['stmts'];
        // Can be null, a single statement, or (possibly) a scalar instead of a node?
        if (!($stmts instanceof Node)) {
            return $node;
        }
        $new_stmts = self::applyToStatementList($stmts);
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
    private static function applyToStatementList(Node $statement_list): Node
    {
        if ($statement_list->kind !== ast\AST_STMT_LIST) {
            $statement_list = self::buildStatementList($statement_list->lineno, $statement_list);
        }
        $new_children = [];
        foreach ($statement_list->children as $child_node) {
            if ($child_node instanceof Node) {
                foreach (self::apply($child_node) as $new_child_node) {
                    $new_children[] = $new_child_node;
                }
            } else {
                $new_children[] = $child_node;
            }
        }
        [$new_children, $modified] = self::normalizeStatementList($new_children);
        if (!$modified && $new_children === $statement_list->children) {
            return $statement_list;
        }
        $clone_node = clone($statement_list);
        $clone_node->children = $new_children;
        return $clone_node;
    }

    /**
     * Creates a new node with kind ast\AST_STMT_LIST from a list of 0 or more child nodes.
     */
    private static function buildStatementList(int $lineno, Node ...$child_nodes): Node
    {
        return new Node(
            ast\AST_STMT_LIST,
            0,
            $child_nodes,
            $lineno
        );
    }

    /**
     * @param list<?Node|?float|?int|?string|?float|?bool> $statements
     * @return array{0:list<Node>,1:bool} - [New/old list, bool $modified] An equivalent list after simplifying (or the original list)
     */
    private static function normalizeStatementList(array $statements): array
    {
        $modified = false;
        $new_statements = [];
        foreach ($statements as $stmt) {
            $new_statements[] = $stmt;
            if (!($stmt instanceof Node)) {
                continue;
            }
            if ($stmt->kind !== ast\AST_IF) {
                continue;
            }
            // Run normalizeIfStatement again.
            \array_pop($new_statements);
            \array_push($new_statements, ...self::normalizeIfStatement($stmt));
            $modified = $modified || \end($new_statements) !== $stmt;
            continue;
        }
        return [$modified ? $new_statements : $statements, $modified];
    }

    private static function docCommentHasArrayShape(?string $doc_comment, string $tag): bool
    {
        if ($doc_comment === null || $doc_comment === '') {
            return false;
        }
        $pattern = '/@' . $tag . '\s+[^\n]*?(array\{|array<|\w+\[\]|\[[^\]]*\])/';
        return preg_match($pattern, $doc_comment) === 1;
    }

    private static function appendDocCommentLine(string $doc_comment, string $tag, string $line): string
    {
        $trimmed = trim($doc_comment);
        if (substr($trimmed, 0, 3) !== '/**' || substr($trimmed, -2) !== '*/') {
            return '/** ' . $line . ' */';
        }
        $inner = trim(substr($trimmed, 3, -2));
        $existing_lines = $inner === '' ? [] : (preg_split('/\r?\n/', $inner) ?: []);
        $normalized = "/**\n";
        foreach ($existing_lines as $existing_line) {
            $existing_line = trim((string)$existing_line);
            if ($existing_line === '') {
                continue;
            }
            $canonical = ltrim($existing_line, " *\t");
            if (preg_match('/^@' . $tag . '\\b/i', $canonical) === 1 &&
                    preg_match('/^@' . $tag . '\s+[^\n]*?(array\{|array<|\\w+\[\]|\[[^\]]*\])/i', $canonical) !== 1) {
                continue;
            }
            if ($existing_line[0] === '*') {
                $normalized .= ' ' . $existing_line . "\n";
            } else {
                $normalized .= ' * ' . $existing_line . "\n";
            }
        }
        $normalized .= ' * ' . $line . "\n */";
        return $normalized;
    }

    private static function ensureDocCommentForArray(Node $node, string $tag): void
    {
        $doc_comment = $node->children['docComment'] ?? null;
        if (self::docCommentHasArrayShape($doc_comment, $tag)) {
            return;
        }
        $line = '@' . $tag . ' array<array-key,mixed>';
        if ($doc_comment === null || $doc_comment === '') {
            $node->children['docComment'] = $tag === 'return' ? self::SYNTHETIC_DOC_COMMENT_RETURN : self::SYNTHETIC_DOC_COMMENT_VAR;
            return;
        }
        $node->children['docComment'] = self::appendDocCommentLine($doc_comment, $tag, $line);
    }

    private static function getTrimMaxElementsPerLevel(): int
    {
        $value = (int)Config::getValue('ast_trim_max_elements_per_level');
        return $value > 0 ? $value : 1;
    }

    private static function getTrimMaxTotalElements(): int
    {
        $value = (int)Config::getValue('ast_trim_max_total_elements');
        return $value > 0 ? $value : 1;
    }

    private static function applyToPropertyGroup(Node $node): Node
    {
        $props_node = $node->children['props'] ?? null;
        if (!($props_node instanceof Node)) {
            return $node;
        }
        $props_children = $props_node->children;
        $child_modified = false;
        foreach ($props_children as $index => $child) {
            if (!($child instanceof Node)) {
                continue;
            }
            $new_child = self::simplifyPropertyElement($child);
            if ($new_child !== $child) {
                if (!$child_modified) {
                    $props_children = $props_node->children;
                    $child_modified = true;
                }
                $props_children[$index] = $new_child;
            }
        }
        $updated_props = $child_modified ? clone($props_node) : $props_node;
        if ($child_modified) {
            $updated_props->children = $props_children;
        }
        [$final_props, $trimmed_child] = self::trimDescendantArrays($updated_props);
        if (!$child_modified && !$trimmed_child) {
            return $node;
        }
        $clone = clone($node);
        $clone->children['props'] = $final_props;
        return $clone;
    }

    private static function simplifyPropertyElement(Node $prop): Node
    {
        $default = $prop->children['default'] ?? null;
        $new_default = $default;
        $trimmed = false;
        if ($default instanceof Node) {
            if ($default->kind === ast\AST_ARRAY) {
                [$new_default, , $default_trimmed] = self::trimArrayNode($default, self::getTrimMaxTotalElements());
                if ($default_trimmed) {
                    $trimmed = true;
                }
            } else {
                [$new_default, $default_trimmed] = self::trimDescendantArrays($default);
                if ($default_trimmed) {
                    $trimmed = true;
                }
            }
        }
        if ($new_default === $default && !$trimmed) {
            return $prop;
        }
        $clone = clone($prop);
        $clone->children['default'] = $new_default;
        if ($trimmed) {
            self::ensureDocCommentForArray($clone, 'var');
        }
        return $clone;
    }

    private static function applyToAssignment(Node $node): Node
    {
        $expr = $node->children['expr'] ?? null;
        $var = $node->children['var'] ?? null;

        $expr_trimmed = false;
        $new_expr = $expr;
        if ($expr instanceof Node) {
            if ($expr->kind === ast\AST_ARRAY) {
                [$new_expr, , $expr_trimmed] = self::trimArrayNode($expr, self::getTrimMaxTotalElements());
            } else {
                [$new_expr, $expr_trimmed] = self::trimDescendantArrays($expr);
            }
        }

        $new_var = $var;
        $var_modified = false;
        if ($var instanceof Node) {
            [$new_var, $var_trimmed] = self::trimDescendantArrays($var);
            $var_modified = $var_trimmed || $new_var !== $var;
        }

        if (!$expr_trimmed && !$var_modified && $new_expr === $expr) {
            return $node;
        }
        $clone = clone($node);
        $clone->children['expr'] = $new_expr;
        $clone->children['var'] = $new_var;
        if ($expr_trimmed) {
            self::ensureDocCommentForArray($clone, 'var');
        }
        return $clone;
    }

    private static function applyToReturn(Node $node): Node
    {
        $expr = $node->children['expr'] ?? null;
        if (!($expr instanceof Node)) {
            return $node;
        }
        if ($expr->kind === ast\AST_ARRAY) {
            [$new_expr, , $expr_trimmed] = self::trimArrayNode($expr, self::getTrimMaxTotalElements());
        } else {
            [$new_expr, $expr_trimmed] = self::trimDescendantArrays($expr);
        }
        if ($new_expr === $expr && !$expr_trimmed) {
            return $node;
        }
        $clone = clone($node);
        $clone->children['expr'] = $new_expr;
        if ($expr_trimmed) {
            self::ensureDocCommentForArray($clone, 'return');
        }
        return $clone;
    }

    private static function applyToStatic(Node $node): Node
    {
        $default = $node->children['default'] ?? null;
        $var = $node->children['var'] ?? null;

        $default_trimmed = false;
        $new_default = $default;
        if ($default instanceof Node) {
            if ($default->kind === ast\AST_ARRAY) {
                [$new_default, , $default_trimmed] = self::trimArrayNode($default, self::getTrimMaxTotalElements());
            } else {
                [$new_default, $default_trimmed] = self::trimDescendantArrays($default);
            }
        }
        $new_var = $var;
        if ($var instanceof Node) {
            [$new_var] = self::trimDescendantArrays($var);
        }
        if ($new_default === $default && $new_var === $var && !$default_trimmed) {
            return $node;
        }
        $clone = clone($node);
        $clone->children['default'] = $new_default;
        $clone->children['var'] = $new_var;
        if ($default_trimmed) {
            self::ensureDocCommentForArray($clone, 'var');
        }
        return $clone;
    }

    /**
     * @return array{0:Node,1:int,2:bool}
     */
    private static function trimArrayNode(Node $array_node, int $budget): array
    {
        if ($array_node->kind !== ast\AST_ARRAY) {
            return [$array_node, 0, false];
        }
        if ($budget <= 0) {
            if (self::arrayHasPossibleSideEffects($array_node)) {
                return [$array_node, 0, false];
            }
            if (!$array_node->children) {
                return [$array_node, 0, false];
            }
            $clone = clone($array_node);
            $clone->children = [];
            return [$clone, 0, true];
        }
        $children = $array_node->children;
        $total = count($children);
        if ($total === 0) {
            return [$array_node, 0, false];
        }
        $allowed = min($budget, self::getTrimMaxElementsPerLevel(), $total);
        $indexes = self::selectRepresentativeIndexes($total, $allowed);

        $side_effect_indexes = [];
        foreach ($children as $index => $child) {
            if ($child instanceof Node && self::arrayElementHasPossibleSideEffects($child)) {
                $side_effect_indexes[] = $index;
            }
        }
        if ($side_effect_indexes) {
            $indexes = array_values(array_unique(array_merge($indexes, $side_effect_indexes)));
            sort($indexes, SORT_NUMERIC);
        }

        $extra_budget = max(0, $budget - count($indexes));
        $nested_budgets = self::distributeBudget($extra_budget, count($indexes));

        $new_children = [];
        $total_used = 0;
        $modified = count($indexes) !== $total;
        foreach ($indexes as $position => $index) {
            $child = $children[$index];
            if (!($child instanceof Node)) {
                $total_used += 1;
                $new_children[] = $child;
                continue;
            }
            [$new_child, $child_used, $child_modified] = self::trimArrayElement($child, $nested_budgets[$position] ?? 0);
            if ($child_modified) {
                $modified = true;
            }
            $total_used += $child_used;
            $new_children[] = $new_child;
        }
        if (!$modified) {
            return [$array_node, $total_used, false];
        }
        $clone = clone($array_node);
        $clone->children = array_values($new_children);
        return [$clone, $total_used, true];
    }

    private static function arrayElementHasPossibleSideEffects(Node $element): bool
    {
        if ($element->kind !== ast\AST_ARRAY_ELEM) {
            return true;
        }
        if (!self::isExpressionWithoutSideEffects($element->children['key'] ?? null)) {
            return true;
        }
        return !self::isExpressionWithoutSideEffects($element->children['value'] ?? null);
    }

    private static function arrayHasPossibleSideEffects(Node $array_node): bool
    {
        if ($array_node->kind !== ast\AST_ARRAY) {
            return false;
        }
        foreach ($array_node->children as $child) {
            if ($child instanceof Node && self::arrayElementHasPossibleSideEffects($child)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{0:Node,1:int,2:bool}
     */
    private static function trimArrayElement(Node $element, int $nested_budget): array
    {
        $used = 1;
        $modified = false;
        $new_element = $element;

        $key = $element->children['key'] ?? null;
        if ($key instanceof Node) {
            if ($key->kind === ast\AST_ARRAY) {
                [$new_key, $key_used, $key_modified] = self::trimArrayNode($key, $nested_budget);
                if ($key_modified) {
                    $new_element = clone($new_element);
                    $new_element->children['key'] = $new_key;
                    $modified = true;
                }
                $used += $key_used;
                $nested_budget = max(0, $nested_budget - $key_used);
            } else {
                [$new_key] = self::trimDescendantArrays($key);
                if ($new_key !== $key) {
                    $new_element = clone($new_element);
                    $new_element->children['key'] = $new_key;
                    $modified = true;
                }
            }
        }

        $value = $element->children['value'] ?? null;
        if ($value instanceof Node) {
            if ($value->kind === ast\AST_ARRAY) {
                [$new_value, $value_used, $value_modified] = self::trimArrayNode($value, $nested_budget);
                if ($value_modified) {
                    if ($new_element === $element) {
                        $new_element = clone($element);
                    }
                    $new_element->children['value'] = $new_value;
                    $modified = true;
                }
                $used += $value_used;
            } else {
                [$new_value] = self::trimDescendantArrays($value);
                if ($new_value !== $value) {
                    if ($new_element === $element) {
                        $new_element = clone($element);
                    }
                    $new_element->children['value'] = $new_value;
                    $modified = true;
                }
            }
        }
        return [$modified ? $new_element : $element, $used, $modified];
    }

    /**
     * @return list<int>
     */
    private static function selectRepresentativeIndexes(int $total, int $limit): array
    {
        if ($limit >= $total) {
            return range(0, $total - 1);
        }
        if ($limit <= 0) {
            return [];
        }
        if ($limit === 1) {
            return [0];
        }
        $indexes = [];
        for ($i = 0; $i < $limit; $i++) {
            $indexes[] = intdiv($i * ($total - 1), $limit - 1);
        }
        $indexes = array_values(array_unique($indexes));
        if (count($indexes) < $limit) {
            for ($i = 0; $i < $total && count($indexes) < $limit; $i++) {
                if (!in_array($i, $indexes, true)) {
                    $indexes[] = $i;
                }
            }
            sort($indexes);
        }
        return $indexes;
    }

    /**
     * @return list<int>
     */
    private static function distributeBudget(int $budget, int $count): array
    {
        if ($count <= 0 || $budget <= 0) {
            return array_fill(0, $count, 0);
        }
        $base = intdiv($budget, $count);
        $remainder = $budget % $count;
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $extra = $base;
            if ($i < $remainder) {
                $extra++;
            }
            $result[] = $extra;
        }
        return $result;
    }

    /**
     * @return array{0:Node,1:bool}
     */
    private static function trimDescendantArrays(Node $node): array
    {
        $children = $node->children;
        $modified = false;
        $trimmed = false;
        foreach ($children as $key => $child) {
            if (!($child instanceof Node)) {
                continue;
            }
            if ($child->kind === ast\AST_ARRAY) {
                [$new_child, , $child_trimmed] = self::trimArrayNode($child, self::getTrimMaxTotalElements());
            } else {
                [$new_child, $child_trimmed] = self::trimDescendantArrays($child);
            }
            if ($child_trimmed) {
                $trimmed = true;
            }
            if ($new_child !== $child) {
                if (!$modified) {
                    $children = $node->children;
                    $modified = true;
                }
                $children[$key] = $new_child;
            }
        }
        if (!$modified) {
            return [$node, $trimmed];
        }
        $clone = clone($node);
        $clone->children = $children;
        return [$clone, $trimmed];
    }

    /**
     * Replaces the last node in a list with a list of 0 or more nodes
     * @param list<Node> $nodes
     * @param Node ...$new_statements
     */
    private static function replaceLastNodeWithNodeList(array &$nodes, Node...$new_statements): void
    {
        if (\array_pop($nodes) === false) {
            throw new AssertionError("Saw an unexpected empty node list");
        }
        foreach ($new_statements as $stmt) {
            $nodes[] = $stmt;
        }
    }

    public const NON_SHORT_CIRCUITING_BINARY_OPERATOR_FLAGS = [
        flags\BINARY_BOOL_XOR,
        flags\BINARY_IS_IDENTICAL,
        flags\BINARY_IS_NOT_IDENTICAL,
        flags\BINARY_IS_EQUAL,
        flags\BINARY_IS_NOT_EQUAL,
        flags\BINARY_IS_SMALLER,
        flags\BINARY_IS_SMALLER_OR_EQUAL,
        flags\BINARY_IS_GREATER,
        flags\BINARY_IS_GREATER_OR_EQUAL,
        flags\BINARY_SPACESHIP,
    ];

    /**
     * If this returns true, the expression has no side effects, and can safely be reordered.
     * (E.g. returns true for `MY_CONST` or `false` in `if (MY_CONST === ($x = y))`
     *
     * @param Node|string|float|int|null $node
     * @internal the way this behaves may change
     * @see ScopeImpactCheckingVisitor::hasPossibleImpact() for a more general check
     */
    public static function isExpressionWithoutSideEffects(Node|float|int|string|null $node): bool
    {
        if ($node === null) {
            return true;
        }
        if (!($node instanceof Node)) {
            return true;
        }
        switch ($node->kind) {
            case ast\AST_CONST:
            case ast\AST_MAGIC_CONST:
            case ast\AST_NAME:
                return true;
            case ast\AST_UNARY_OP:
                return self::isExpressionWithoutSideEffects($node->children['expr']);
            case ast\AST_BINARY_OP:
                return self::isExpressionWithoutSideEffects($node->children['left']) &&
                    self::isExpressionWithoutSideEffects($node->children['right']);
            case ast\AST_CLASS_CONST:
            case ast\AST_CLASS_NAME:
                return self::isExpressionWithoutSideEffects($node->children['class']);
            case ast\AST_ARRAY:
                foreach ($node->children as $child) {
                    if (!($child instanceof Node) || $child->kind !== ast\AST_ARRAY_ELEM) {
                        return false;
                    }
                    if (!self::isExpressionWithoutSideEffects($child->children['key'] ?? null) ||
                            !self::isExpressionWithoutSideEffects($child->children['value'] ?? null)) {
                        return false;
                    }
                }
                return true;
            case ast\AST_ARRAY_ELEM:
                return self::isExpressionWithoutSideEffects($node->children['key'] ?? null) &&
                    self::isExpressionWithoutSideEffects($node->children['value'] ?? null);
            default:
                return false;
        }
    }

    /**
     * Converts an if statement to one which is easier for phan to analyze
     * E.g. repeatedly makes these conversions
     * if (A && B) {X} -> if (A) { if (B) {X}}
     * if ($var = A) {X} -> $var = A; if ($var) {X}
     * @return non-empty-list<Node> - One or more nodes created from $original_node.
     *        Will return [$original_node] if no modifications were made.
     */
    private static function normalizeIfStatement(Node $original_node): array
    {
        $nodes = [$original_node];
        // Repeatedly apply these rules
        do {
            $old_nodes = $nodes;
            $node = $nodes[count($nodes) - 1];
            $node->flags = 0;
            $if_cond = $node->children[0]->children['cond'];
            if (!($if_cond instanceof Node)) {
                break;  // No transformation rules apply here.
            }

            if ($if_cond->kind === ast\AST_UNARY_OP &&
                $if_cond->flags === flags\UNARY_BOOL_NOT) {
                $cond_node = $if_cond->children['expr'];
                if ($cond_node instanceof Node &&
                        $cond_node->kind === ast\AST_UNARY_OP &&
                        $cond_node->flags === flags\UNARY_BOOL_NOT) {
                    self::replaceLastNodeWithNodeList($nodes, self::applyIfDoubleNegateReduction($node));
                    continue;
                }
                if (count($node->children) === 1) {
                    self::replaceLastNodeWithNodeList($nodes, self::applyIfNegatedToIfElseReduction($node));
                    continue;
                }
            }
            if ($if_cond->kind === ast\AST_BINARY_OP && in_array($if_cond->flags, self::NON_SHORT_CIRCUITING_BINARY_OPERATOR_FLAGS, true)) {
                // if (($var = A) === B) {X} -> $var = A; if ($var === B) { X}
                $if_cond_children = $if_cond->children;
                if (in_array($if_cond_children['left']->kind ?? 0, [ast\AST_ASSIGN, ast\AST_ASSIGN_REF], true) &&
                        ($if_cond_children['left']->children['var']->kind ?? 0) === ast\AST_VAR &&
                        self::isExpressionWithoutSideEffects($if_cond_children['right'])) {
                    self::replaceLastNodeWithNodeList($nodes, ...self::applyAssignInLeftSideOfBinaryOpReduction($node));
                    continue;
                }
                if (in_array($if_cond_children['right']->kind ?? 0, [ast\AST_ASSIGN, ast\AST_ASSIGN_REF], true) &&
                        ($if_cond_children['right']->children['var']->kind ?? 0) === ast\AST_VAR &&
                        self::isExpressionWithoutSideEffects($if_cond_children['left'])) {
                    self::replaceLastNodeWithNodeList($nodes, ...self::applyAssignInRightSideOfBinaryOpReduction($node));
                    continue;
                }
                // TODO: If the left-hand side is a constant or class constant or literal, that's safe to rearrange as well
                // (But `foo($y = something()) && $x = $y` is not safe to rearrange)
            }
            if (count($node->children) === 1) {
                if ($if_cond->kind === ast\AST_BINARY_OP &&
                        $if_cond->flags === flags\BINARY_BOOL_AND) {
                    self::replaceLastNodeWithNodeList($nodes, self::applyIfAndReduction($node));
                    // if (A && B) {X} -> if (A) { if (B) {X}}
                    // Do this, unless there is an else statement that can be executed.
                    continue;
                }
            } elseif (count($node->children) === 2) {
                if ($if_cond->kind === ast\AST_UNARY_OP &&
                        $if_cond->flags === flags\UNARY_BOOL_NOT &&
                        $node->children[1]->children['cond'] === null) {
                    self::replaceLastNodeWithNodeList($nodes, self::applyIfNegateReduction($node));
                    continue;
                }
            } elseif (count($node->children) >= 3) {
                self::replaceLastNodeWithNodeList($nodes, self::applyIfChainReduction($node));
                continue;
            }
            if ($if_cond->kind === ast\AST_ASSIGN &&
                    ($if_cond->children['var']->kind ?? null) === ast\AST_VAR) {
                // if ($var = A) {X} -> $var = A; if ($var) {X}
                // do this whether or not there is an else.
                // TODO: Could also reduce `if (($var = A) && B) {X} else if (C) {Y} -> $var = A; ....
                self::replaceLastNodeWithNodeList($nodes, ...self::applyIfAssignReduction($node));
                continue;
            }
        } while ($old_nodes !== $nodes);
        return $nodes;
    }

    /**
     * Converts a while statement to one which is easier for phan to analyze
     * E.g. repeatedly makes these conversions
     * while (A && B) {X} -> while (A) { if (!B) {break;} X}
     * while (!!A) {X} -> while (A) { X }
     * @return array{0:Node} - An array with a single while statement
     *        Will return [$original_node] if no modifications were made.
     */
    private static function normalizeWhileStatement(Node $original_node): array
    {
        $node = $original_node;
        // Repeatedly apply these rules
        while (true) {
            $while_cond = $node->children['cond'];
            if (!($while_cond instanceof Node)) {
                break;  // No transformation rules apply here.
            }

            if ($while_cond->kind === ast\AST_UNARY_OP &&
                $while_cond->flags === flags\UNARY_BOOL_NOT) {
                $cond_node = $while_cond->children['expr'];
                if ($cond_node instanceof Node &&
                        $cond_node->kind === ast\AST_UNARY_OP &&
                        $cond_node->flags === flags\UNARY_BOOL_NOT) {
                    $node = self::applyWhileDoubleNegateReduction($node);
                    continue;
                }
                break;
            }
            if ($while_cond->kind === ast\AST_BINARY_OP &&
                    $while_cond->flags === flags\BINARY_BOOL_AND) {
                // TODO: Also support `and` operator.
                $node = self::applyWhileAndReduction($node);
                // while (A && B) {X} -> while (A) { if (!B) {break;} X}
                // Do this, unless there is an else statement that can be executed.
                continue;
            }
            break;
        }

        return [$node];
    }

    /**
     * Converts a for statement to one which is easier for phan to analyze
     * E.g. repeatedly makes these conversions
     * for (init; !!cond; loop) -> for (init; cond; loop)
     * @return array{0:Node} - An array with a single for statement.
     *        Will return [$node] if no modifications were made.
     */
    private static function normalizeForStatement(Node $node): array
    {
        // Repeatedly apply these rules
        while (true) {
            $for_cond_list = $node->children['cond'];
            if (!($for_cond_list instanceof Node)) {
                break;  // No transformation rules apply here.
            }

            $for_cond = \end($for_cond_list->children);

            if (!($for_cond instanceof Node)) {
                break;
            }
            if ($for_cond->kind === ast\AST_UNARY_OP &&
                $for_cond->flags === flags\UNARY_BOOL_NOT) {
                $cond_node = $for_cond->children['expr'];
                if ($cond_node instanceof Node &&
                        $cond_node->kind === ast\AST_UNARY_OP &&
                        $cond_node->flags === flags\UNARY_BOOL_NOT) {
                    $node = self::applyForDoubleNegateReduction($node);
                    continue;
                }
            }
            break;
        }

        return [$node];
    }

    /**
     * if (($var = A) === B) {X} -> $var = A; if ($var === B) { X }
     *
     * @return array{0:Node,1:Node}
     * @suppress PhanTypePossiblyInvalidCloneNotObject this was checked by the caller.
     */
    private static function applyAssignInLeftSideOfBinaryOpReduction(Node $node): array
    {
        $inner_assign_statement = $node->children[0]->children['cond']->children['left'];
        if (!($inner_assign_statement instanceof Node)) {
            throw new AssertionError('Expected $inner_assign_statement instanceof Node');
        }
        $inner_assign_var = $inner_assign_statement->children['var'];

        if ($inner_assign_var->kind !== ast\AST_VAR) {
            throw new AssertionError('Expected $inner_assign_var->kind === ast\AST_VAR');
        }

        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond']->children['left'] = $inner_assign_var;
        $new_node_elem->flags = 0;
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno;
        $new_node->flags = 0;
        return [$inner_assign_statement, $new_node];
    }

    /**
     * if (B === ($var = A)) {X} -> $var = A; if (B === $var) { X }
     *
     * @return array{0:Node,1:Node}
     * @suppress PhanTypePossiblyInvalidCloneNotObject this was checked by the caller.
     */
    private static function applyAssignInRightSideOfBinaryOpReduction(Node $node): array
    {
        $inner_assign_statement = $node->children[0]->children['cond']->children['right'];
        $inner_assign_var = $inner_assign_statement->children['var'];

        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond']->children['right'] = $inner_assign_var;
        $new_node_elem->flags = 0;
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno;
        $new_node->flags = 0;
        return [$inner_assign_statement, $new_node];
    }

    /**
     * Creates a new node with kind ast\AST_IF from two branches
     */
    private static function buildIfNode(Node $l, Node $r): Node
    {
        return new Node(
            ast\AST_IF,
            0,
            [$l, $r],
            $l->lineno
        );
    }

    /**
     * maps if (A) {X} elseif (B) {Y} else {Z} -> if (A) {Y} else { if (B) {Y} else {Z}}
     */
    private static function applyIfChainReduction(Node $node): Node
    {
        $children = $node->children;  // Copy of array of Nodes of type IF_ELEM
        if (count($children) <= 2) {
            return $node;
        }
        while (count($children) > 2) {
            $r = array_pop($children);
            $l = array_pop($children);
            if (!($l instanceof Node && $r instanceof Node)) {
                throw new AssertionError("Expected to have AST_IF_ELEM nodes");
            }
            $l->children['stmts']->flags = 0;
            $r->children['stmts']->flags = 0;
            $inner_if_node = self::buildIfNode($l, $r);
            $new_r = new Node(
                ast\AST_IF_ELEM,
                0,
                [
                    'cond' => null,
                    'stmts' => self::buildStatementList($inner_if_node->lineno, ...(self::normalizeIfStatement($inner_if_node))),
                ],
                0
            );
            $children[] = $new_r;
        }
        // $children is an array of 2 nodes of type IF_ELEM
        return new Node(ast\AST_IF, 0, $children, $node->lineno);
    }

    /**
     * Converts if (A && B) {X}` -> `if (A) { if (B){X}}`
     * @return Node simplified node logically equivalent to $node, with kind ast\AST_IF.
     * @suppress PhanTypePossiblyInvalidCloneNotObject this was checked by the caller.
     */
    private static function applyIfAndReduction(Node $node): Node
    {
        if (count($node->children) !== 1) {
            throw new AssertionError('Expected an if statement with no else/elseif statements');
        }
        $inner_node_elem = clone($node->children[0]);  // AST_IF_ELEM
        $inner_node_elem->children['cond'] = $inner_node_elem->children['cond']->children['right'];
        $inner_node_elem->flags = 0;
        $inner_node_lineno = $inner_node_elem->lineno;

        // Normalize code such as `if (A && (B && C)) {...}` recursively.
        $inner_node_stmts = self::normalizeIfStatement(new Node(
            ast\AST_IF,
            0,
            [$inner_node_elem],
            $inner_node_lineno
        ));

        $inner_node_stmt_list = new Node(ast\AST_STMT_LIST, 0, $inner_node_stmts, $inner_node_lineno);
        $outer_node_elem = clone($node->children[0]);  // AST_IF_ELEM
        $outer_node_elem->children['cond'] = $node->children[0]->children['cond']->children['left'];
        $outer_node_elem->children['stmts'] = $inner_node_stmt_list;
        $outer_node_elem->flags = 0;
        return new Node(
            ast\AST_IF,
            0,
            [$outer_node_elem],
            $node->lineno
        );
    }

    /**
     * Converts `while (A && B) {X}` -> `while (A) { if (!B) { break;} X}`
     * @return Node simplified node logically equivalent to $node, with kind ast\AST_IF.
     */
    private static function applyWhileAndReduction(Node $node): Node
    {
        $cond_node = $node->children['cond'];
        $right_node = $cond_node->children['right'];
        $lineno = $right_node->lineno ?? $cond_node->lineno;
        $conditional_break_elem = self::makeBreakWithNegatedConditional($right_node, $lineno);

        return new Node(
            ast\AST_WHILE,
            0,
            [
                'cond' => $cond_node->children['left'],
                'stmts' => new Node(
                    ast\AST_STMT_LIST,
                    0,
                    array_merge([$conditional_break_elem], $node->children['stmts']->children),
                    $lineno
                ),
            ],
            $node->lineno
        );
    }

    /**
     * Creates a Node for `if (!COND) { break; }`
     */
    private static function makeBreakWithNegatedConditional(Node|float|int|string $cond_node, int $lineno): Node
    {
        $break_if_elem = new Node(
            ast\AST_IF_ELEM,
            0,
            [
                'cond' => new Node(
                    ast\AST_UNARY_OP,
                    flags\UNARY_BOOL_NOT,
                    ['expr' => $cond_node],
                    $lineno
                ),
                'stmts' => new Node(
                    ast\AST_STMT_LIST,
                    0,
                    [new Node(ast\AST_BREAK, 0, ['depth' => null], $lineno)],
                    $lineno
                ),
            ],
            $lineno
        );
        return new Node(
            ast\AST_IF,
            0,
            [$break_if_elem],
            $lineno
        );
    }

    /**
     * Converts if ($x = A) {Y} -> $x = A; if ($x) {Y}
     * This allows analyzing variables set in if blocks outside of the `if` block
     * @return array{0:Node,1:Node} [$outer_assign_statement, $new_node]
     * @suppress PhanTypePossiblyInvalidCloneNotObject this was checked by the caller.
     */
    private static function applyIfAssignReduction(Node $node): array
    {
        $outer_assign_statement = $node->children[0]->children['cond'];
        if (!($outer_assign_statement instanceof Node)) {
            throw new AssertionError('Expected condition of first if statement (with assignment as condition) to be a Node');
        }
        $new_node_elem = clone($node->children[0]);
        $new_node_elem->children['cond'] = $new_node_elem->children['cond']->children['var'];
        $new_node_elem->flags = 0;
        $new_node = clone($node);
        $new_node->children[0] = $new_node_elem;
        $new_node->lineno = $new_node_elem->lineno;
        $new_node->flags = 0;
        return [$outer_assign_statement, $new_node];
    }

    /**
     * Converts if (!x) {Y} else {Z} -> if (x) {Z} else {Y}
     * This improves Phan's analysis for cases such as `if (!is_string($x))`.
     * @suppress PhanTypePossiblyInvalidCloneNotObject this was checked by the caller.
     */
    private static function applyIfNegateReduction(Node $node): Node
    {
        if (!(
            count($node->children) === 2 &&
            $node->children[0]->children['cond']->flags === flags\UNARY_BOOL_NOT &&
            $node->children[1]->children['cond'] === null
        )) {
            throw new AssertionError('Failed precondition of ' . __METHOD__);
        }
        $new_node = clone($node);
        $new_node->children = [clone($new_node->children[1]), clone($new_node->children[0])];
        $new_node->children[0]->children['cond'] = $node->children[0]->children['cond']->children['expr'];
        $new_node->children[1]->children['cond'] = null;
        $new_node->flags = 0;
        // @phan-suppress-next-line PhanUndeclaredProperty used by EmptyStatementListPlugin
        $new_node->is_simplified = true;
        return $new_node;
    }

    /**
     * Converts if (!!(x)) {Y} -> if (x) {Y}
     * This improves Phan's analysis for cases such as `if (!!x)`
     * @suppress PhanTypePossiblyInvalidCloneNotObject this was checked by the caller.
     */
    private static function applyIfDoubleNegateReduction(Node $node): Node
    {
        if (!(
            $node->children[0]->children['cond']->flags === flags\UNARY_BOOL_NOT &&
            $node->children[0]->children['cond']->children['expr']->flags === flags\UNARY_BOOL_NOT
        )) {
            throw new AssertionError('Failed precondition of ' . __METHOD__);
        }

        $new_cond = $node->children[0]->children['cond']->children['expr']->children['expr'];
        $new_node = clone($node);
        $new_node->flags = 0;
        $new_node->children[0] = clone($node->children[0]);
        $new_node->children[0]->flags = 0;
        $new_node->children[0]->children['cond'] = $new_cond;

        return $new_node;
    }

    /**
     * Converts while (!!(x)) {Y} -> if (x) {Y}
     * This improves Phan's analysis for cases such as `if (!!x)`
     */
    private static function applyWhileDoubleNegateReduction(Node $node): Node
    {
        if (!(
            $node->children['cond']->flags === flags\UNARY_BOOL_NOT &&
            $node->children['cond']->children['expr']->flags === flags\UNARY_BOOL_NOT
        )) {
            throw new AssertionError('Failed precondition of ' . __METHOD__);
        }

        return new Node(
            ast\AST_WHILE,
            0,
            [
                'cond' => $node->children['cond']->children['expr']->children['expr'],
                'stmts' => $node->children['stmts']
            ],
            $node->lineno
        );
    }

    /**
     * Converts for (INIT; !!(x); LOOP) {Y} -> if (INIT; x; LOOP) {Y}
     * This improves Phan's analysis for cases such as `if (!!x)`
     */
    private static function applyForDoubleNegateReduction(Node $node): Node
    {
        $children = $node->children;
        $cond_node_list = $children['cond']->children;
        $cond_node = array_pop($cond_node_list);
        if (!(
            $cond_node->flags === flags\UNARY_BOOL_NOT &&
            $cond_node->children['expr']->flags === flags\UNARY_BOOL_NOT
        )) {
            throw new AssertionError('Failed precondition of ' . __METHOD__);
        }
        $cond_node_list[] = $cond_node->children['expr']->children['expr'];

        $children['cond'] = new ast\Node(
            ast\AST_EXPR_LIST,
            0,
            $cond_node_list,
            $children['cond']->lineno
        );

        return new Node(
            ast\AST_FOR,
            0,
            $children,
            $node->lineno
        );
    }

    private static function applyIfNegatedToIfElseReduction(Node $node): Node
    {
        if (count($node->children) !== 1) {
            throw new AssertionError("Expected one child node");
        }
        $if_elem = $node->children[0];
        if ($if_elem->children['cond']->flags !== flags\UNARY_BOOL_NOT) {
            throw new AssertionError("Expected condition to begin with unary boolean negation operator");
        }
        $lineno = $if_elem->lineno;
        $new_else_elem = new Node(
            ast\AST_IF_ELEM,
            0,
            [
                'cond' => null,
                'stmts' => $if_elem->children['stmts'],
            ],
            $lineno
        );
        $new_if_elem = new Node(
            ast\AST_IF_ELEM,
            0,
            [
                'cond' => $if_elem->children['cond']->children['expr'],
                'stmts' => new Node(ast\AST_STMT_LIST, 0, [], $if_elem->lineno),
            ],
            $lineno
        );
        return new Node(
            ast\AST_IF,
            0,
            [$new_if_elem, $new_else_elem],
            $node->lineno
        );
    }

    /**
     * Recurses on a list of 0 or more catch statements. (as in try/catch)
     * Returns an equivalent list of catch AST nodes (or the original if no changes were made)
     */
    private static function normalizeCatchesList(Node $catches): Node
    {
        $list = $catches->children;
        $new_list = array_map(
            static function (Node $node): Node {
                return self::applyToStmts($node);
            },
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument should be impossible to be float
            $list
        );
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
    private static function normalizeTryStatement(Node $node): Node
    {
        $try = $node->children['try'];
        $catches = $node->children['catches'];
        $finally = $node->children['finally'] ?? null;
        $new_try = self::applyToStatementList($try);
        $new_catches = $catches ? self::normalizeCatchesList($catches) : $catches;
        $new_finally = $finally ? self::applyToStatementList($finally) : $finally;
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

    /**
     * Returns a Node that represents $node after all of the AST simplification steps.
     *
     * $node is not modified. This will reuse descendant nodes that didn't change.
     */
    public static function applyStatic(Node $node): Node
    {
        $rewriter = new self();
        $nodes = $rewriter->apply($node);
        if (count($nodes) !== 1) {
            throw new AssertionError("Expected applying simplifier to a statement list would return an array with one statement list");
        }
        return $nodes[0];
    }
}
