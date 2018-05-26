<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

use ast\Node;

/**
 * This represents a summary of all of the definitions and uses of all variable within a scope.
 */
final class VariableGraph
{
    /**
     * @var array<string,array<int,array<int,true>>>
     *
     * Maps variable name to (definition id to (list of uses of that given definition))
     */
    public $defs_uses = [];

    /**
     * @var array<string,array<int,int>>
     *
     * Maps variable id to variable line
     */
    public $def_lines = [];

    const IS_REFERENCE = 1<<0;
    const IS_GLOBAL    = 1<<1;
    const IS_STATIC    = 1<<2;

    /**
     * @var array<string,int> maps variable names to whether
     *    they have ever occurred as a given self::IS_* category in the current scope
     */
    public $variable_types = [];

    public function __construct()
    {
    }

    public function recordVariableDefinition(string $name, Node $node)
    {
        // TODO: Measure performance against SplObjectHash
        $id = \spl_object_id($node);
        if (!isset($this->defs_uses[$name][$id])) {
            $this->defs_uses[$name][$id] = [];
        }
        $this->def_lines[$name][$id] = $node->lineno;
    }

    public function markAsReference(string $name)
    {
        $this->variable_types[$name] = (($this->variable_types[$name] ?? 0) | self::IS_REFERENCE);
    }
}
