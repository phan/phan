<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\VariableTracker;

use ast\Node;

use function spl_object_id;

/**
 * This represents a summary of all of the definitions and uses of all variable within a scope.
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
final class VariableGraph
{
    /**
     * @var array<string,associative-array<int,associative-array<int,true>>>
     *
     * Maps variable name to (definition id to (set of use ids of that given definition))
     */
    public $def_uses = [];

    /**
     * @var array<string,associative-array<int,int>>
     *
     * Maps variable name to a map from definition id to line number of the node.
     */
    public $def_lines = [];

    /**
     * @var array<string,associative-array<int,Node|int|float|string>>
     *
     * Maps variable name to a set of definition ids and their corresponding constant AST nodes
     */
    public $const_expr_declarations = [];

    /**
     * @var associative-array<int,int>
     *
     * Maps definition ids to information about them (e.g. IS_GLOBAL|IS_STATIC|IS_LOOP_DEF|IS_CAUGHT_EXCEPTION)
     */
    public $def_bitset = [];

    /**
     * @var array<string,int> maps variable names to whether
     *    they have ever occurred as a given self::IS_* category in the current scope
     */
    public $variable_types = [];

    // Are these names/definition ids references, globals, or static
    public const IS_REFERENCE      = 1 << 0;
    public const IS_GLOBAL         = 1 << 1;
    public const IS_STATIC         = 1 << 2;
    // Is this possibly a placeholder loop variable
    public const IS_LOOP_DEF       = 1 << 3;
    // Is this a caught exception
    public const IS_CAUGHT_EXCEPTION = 1 << 4;

    public const IS_REFERENCE_OR_GLOBAL_OR_STATIC = self::IS_REFERENCE | self::IS_GLOBAL | self::IS_STATIC;

    public function __construct()
    {
    }

    /**
     * Record the fact that $node is a definition of the variable with name $name in the scope $scope
     * @param ?(Node|string|int|float) $const_expr is the definition's value a value that could be a constant?
     */
    public function recordVariableDefinition(string $name, Node $node, VariableTrackingScope $scope, $const_expr): void
    {
        // TODO: Measure performance against SplObjectHash
        $id = \spl_object_id($node);
        if (!isset($this->def_uses[$name][$id])) {
            $this->def_uses[$name][$id] = [];
        }
        $this->def_lines[$name][$id] = $node->lineno;
        if ($const_expr !== null) {
            $this->const_expr_declarations[$name][$id] = $const_expr;
        }
        $scope->recordDefinitionById($name, $id);
    }

    /**
     * Records that the variable with name $name was used by Node $node in the given scope.
     *
     * This marks the definitions that are accessible from this scope as being used at $node.
     */
    public function recordVariableUsage(string $name, Node $node, VariableTrackingScope $scope): void
    {
        if (!\array_key_exists($name, $this->variable_types)) {
            // Set this to 0 to record that the variable was used somewhere
            // (it will be overridden later if there are flags to set)
            $this->variable_types[$name] = 0;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty added by ArgumentType analyzer
        if (isset($node->is_reference)) {
            $this->markAsReference($name);
        }
        $defs_for_variable = $scope->getDefinition($name);
        if (!$defs_for_variable) {
            return;
        }
        $node_id = \spl_object_id($node);
        $scope->recordUsageById($name, $node_id);
        foreach ($defs_for_variable as $def_id => $_) {
            if ($def_id !== $node_id) {
                $this->def_uses[$name][$def_id][$node_id] = true;
            }
        }
    }

    /**
     * Record that $name was modified in place
     */
    public function recordVariableModification(string $name): void
    {
        $this->const_expr_declarations[$name][-1] = 0;
    }

    /**
     * @param associative-array<int,mixed> $loop_uses_of_own_variable any array that has node ids for uses of $def_id as keys
     */
    public function recordLoopSelfUsage(string $name, int $def_id, array $loop_uses_of_own_variable): void
    {
        foreach ($loop_uses_of_own_variable as $node_id => $_) {
            // For expressions such as `;$var++;` or `$var += 1;`, don't count the modifying declaration in a loop as a usage - it's unused if nothing else uses that.
            if ($def_id !== $node_id) {
                $this->def_uses[$name][$def_id][$node_id] = true;
            }
        }
    }

    /**
     * Records that the variable with the name $name was used as a reference
     * somewhere within the function body
     */
    public function markAsReference(string $name): void
    {
        $this->markBitForVariableName($name, self::IS_REFERENCE);
    }

    /**
     * Records that the variable with the name $name was declared as a static variable
     * somewhere within the function body
     */
    public function markAsStaticVariable(string $name): void
    {
        $this->markBitForVariableName($name, self::IS_STATIC);
    }

    /**
     * Records that the variable with the name $name was declared as a global variable
     * somewhere within the function body
     */
    public function markAsGlobalVariable(string $name): void
    {
        $this->markBitForVariableName($name, self::IS_GLOBAL);
    }

    /**
     * Marks something as being a loop variable `$v` in `foreach ($arr as $k => $v)`
     * (Common false positive, since there's no way to avoid setting the value)
     *
     * @param Node|string|int|float|null $node
     */
    public function markAsLoopValueNode($node): void
    {
        if ($node instanceof Node) {
            $this->def_bitset[spl_object_id($node)] = self::IS_LOOP_DEF;
        }
    }

    /**
     * Checks if the node for this id is defined as the value in a foreach over keys of an array.
     */
    public function isLoopValueDefinitionId(int $definition_id): bool
    {
        return ($this->def_bitset[$definition_id] ?? 0) === self::IS_LOOP_DEF;
    }

    /**
     * Marks something as being a caught exception `$e` in `catch ($e)`
     * (Common false positive, since there's no way to avoid setting the value)
     *
     * @param Node|int|string|float|null $node
     */
    public function markAsCaughtException($node): void
    {
        if ($node instanceof Node) {
            $this->def_bitset[spl_object_id($node)] = self::IS_CAUGHT_EXCEPTION;
        }
    }

    /**
     * Checks if the node for this id is defined as a caught exception
     */
    public function isCaughtException(int $definition_id): bool
    {
        return ($this->def_bitset[$definition_id] ?? 0) === self::IS_CAUGHT_EXCEPTION;
    }

    /**
     * Marks something as being a declaration of a global
     */
    public function markAsGlobal(Node $node, VariableTrackingScope $scope): void
    {
        $this->def_bitset[spl_object_id($node)] = self::IS_GLOBAL;
        $name = $node->children['var']->children['name'] ?? null;
        if (\is_string($name)) {
            $this->markAsGlobalVariable($name);
            $this->recordVariableDefinition($name, $node, $scope, null);
        }
    }

    /**
     * Is this definition_id the first declarration of a global?
     */
    public function isGlobal(int $definition_id): bool
    {
        return ($this->def_bitset[$definition_id] ?? 0) === self::IS_GLOBAL;
    }

    private function markBitForVariableName(string $name, int $bit): void
    {
        $this->variable_types[$name] = (($this->variable_types[$name] ?? 0) | $bit);
    }
}
