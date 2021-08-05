<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use InvalidArgumentException;

use function is_string;

/**
 * Utilities for computing uses of an ast\AST_ARROW_FUNC node.
 */
class ArrowFunc
{
    /** @var associative-array<int|string, Node> maps variable names to the first Node where the variable was used.*/
    private $uses = [];

    private function __construct()
    {
    }

    /**
     * Returns the set of variables used by the arrow func $n
     *
     * @param Node $n a Node with kind ast\AST_ARROW_FUNC
     * @return associative-array<int|string, Node>
     */
    public static function getUses(Node $n): array
    {
        if ($n->kind !== ast\AST_ARROW_FUNC) {
            throw new InvalidArgumentException("Expected node kind AST_ARROW_FUNC but got " . ast\get_kind_name($n->kind));
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        return $n->phan_arrow_uses ?? $n->phan_arrow_uses = (new self())->computeUses($n);
    }

    /**
     * @return array<string|int,Node>
     */
    private function computeUses(Node $n): array
    {
        $stmts = $n->children['stmts'];
        if ($stmts instanceof Node) {  // should always be a node
            $this->buildUses($stmts);
            // Iterate over the AST_PARAM nodes and remove their variables.
            // They are variables used within the function, but are not uses from the outer scope.
            foreach ($n->children['params']->children ?? [] as $param) {
                $name = $param->children['name'] ?? null;
                if (\is_string($name)) {
                    unset($this->uses[$name]);
                }
            }
        }
        return $this->uses;
    }

    /**
     * @param int|string $name the name of the variable being used by this arrow func.
     *                         may need to handle `${'0'}`?
     */
    private function recordUse($name, Node $n): void
    {
        if ($name !== 'this') {
            $this->uses[$name] = $this->uses[$name] ?? $n;
        }
    }

    private function buildUses(Node $n): void
    {
        switch ($n->kind) {
            case ast\AST_VAR:
                $name = $n->children['name'];
                if (is_string($name)) {
                    $this->recordUse($name, $n);
                    return;
                }
                break;
            case ast\AST_ARROW_FUNC:
                foreach (self::getUses($n) as $name => $child_node) {
                    $this->recordUse($name, $child_node);
                }
                return;
            case ast\AST_CLOSURE:
                foreach ($n->children['uses']->children ?? [] as $child_node) {
                    if (!$child_node instanceof Node) {
                        continue;
                    }
                    $name = $child_node->children['name'];
                    if (is_string($name)) {
                        $this->recordUse($name, $child_node);
                    }
                }
                return;
            case ast\AST_CLASS:
                foreach ($n->children['args']->children ?? [] as $child_node) {
                    if ($child_node instanceof Node) {
                        $this->buildUses($child_node);
                    }
                }
                return;
        }
        foreach ($n->children as $child_node) {
            if ($child_node instanceof Node) {
                $this->buildUses($child_node);
            }
        }
    }

    /**
     * Record that variable $variable_name exists in the outer scope of the arrow function with node $n
     */
    public static function recordVariableExistsInOuterScope(Node $n, string $variable_name): void
    {
        if ($n->kind !== ast\AST_ARROW_FUNC) {
            throw new InvalidArgumentException("Expected node kind AST_ARROW_FUNC but got " . ast\get_kind_name($n->kind));
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $n->phan_arrow_inherited_vars[$variable_name] = true;
    }
}
