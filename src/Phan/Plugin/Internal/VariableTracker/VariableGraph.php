<?php declare(strict_types=1);

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
     * @var array<string,array<int,array<int,true>>>
     *
     * Maps variable name to (definition id to (list of uses of that given definition))
     */
    public $def_uses = [];

    /**
     * @var array<string,array<int,int>>
     *
     * Maps variable id to line number of the node for a definition ids
     */
    public $def_lines = [];

    /**
     * @var array<string,array<int,Node|int|float|string>>
     *
     * Maps variable id to a set of definition ids and their corresponding constant AST nodes
     */
    public $const_expr_declarations = [];

    /**
     * @var array<int,true>
     *
     * The set of definition ids that are possibly placeholder loop values
     * in foreach over keys.
     */
    public $loop_def_ids = [];

    /**
     * @var array<int,true>
     *
     * The set of definition ids that are caught exceptions in catch blocks.
     */
    public $caught_exception_ids = [];

    /**
     * @var array<string,int> maps variable names to whether
     *    they have ever occurred as a given self::IS_* category in the current scope
     */
    public $variable_types = [];

    const IS_REFERENCE      = 1 << 0;
    const IS_GLOBAL         = 1 << 1;
    const IS_STATIC         = 1 << 2;

    const IS_REFERENCE_OR_GLOBAL_OR_STATIC = self::IS_REFERENCE | self::IS_GLOBAL | self::IS_STATIC;

    public function __construct()
    {
    }

    /**
     * Record the fact that $node is a definition of the variable with name $name in the scope $scope
     * @param ?(Node|string|int|float) $const_expr is the definition's value a value that could be a constant?
     * @return void
     */
    public function recordVariableDefinition(string $name, Node $node, VariableTrackingScope $scope, $const_expr)
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
     * @return void
     */
    public function recordVariableUsage(string $name, Node $node, VariableTrackingScope $scope)
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
    public function recordVariableModification(string $name) {
        $this->const_expr_declarations[$name][-1] = 0;
    }

    /**
     * @param array<int,mixed> $loop_uses_of_own_variable any array that has node ids for uses of $def_id as keys
     * @return void
     */
    public function recordLoopSelfUsage(string $name, int $def_id, array $loop_uses_of_own_variable)
    {
        foreach ($loop_uses_of_own_variable as $node_id => $_) {
            // For expressions such as `;$var++;` or `$var += 1;`, don't count the modifying declaration in a loop as a usage - it's unused if nothing else uses that.
            if ($def_id !== $node_id) {
                $this->def_uses[$name][$def_id][$node_id] = true;
            }
        }
    }

    /**
     * @return void
     */
    public function markAsReference(string $name)
    {
        $this->markBitForVariableName($name, self::IS_REFERENCE);
    }

    /**
     * @return void
     */
    public function markAsStaticVariable(string $name)
    {
        $this->markBitForVariableName($name, self::IS_STATIC);
    }

    /**
     * @return void
     */
    public function markAsGlobalVariable(string $name)
    {
        $this->markBitForVariableName($name, self::IS_GLOBAL);
    }

    /**
     * Marks something as being a loop variable `$v` in `foreach ($arr as $k => $v)`
     * (Common false positive, since there's no way to avoid setting the value)
     *
     * @param Node|string|int|float|null $node
     * @return void
     */
    public function markAsLoopValueNode($node)
    {
        if ($node instanceof Node) {
            $this->loop_def_ids[spl_object_id($node)] = true;
        }
    }

    /**
     * Checks if the node for this id is defined as the value in a foreach over keys of an array.
     */
    public function isLoopValueDefinitionId(int $definition_id) : bool
    {
        return \array_key_exists($definition_id, $this->loop_def_ids);
    }

    /**
     * Marks something as being a caught exception `$e` in `catch ($e)`
     * (Common false positive, since there's no way to avoid setting the value)
     *
     * @param Node|int|string|float|null $node
     * @return void
     */
    public function markAsCaughtException($node)
    {
        if ($node instanceof Node) {
            $this->caught_exception_ids[spl_object_id($node)] = true;
        }
    }

    /**
     * Checks if the node for this id is defined as a caught exception
     */
    public function isCaughtException(int $definition_id) : bool
    {
        return \array_key_exists($definition_id, $this->caught_exception_ids);
    }

    /**
     * @return void
     */
    private function markBitForVariableName(string $name, int $bit)
    {
        $this->variable_types[$name] = (($this->variable_types[$name] ?? 0) | $bit);
    }
}
