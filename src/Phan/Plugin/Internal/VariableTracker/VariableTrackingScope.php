<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

use ast\Node;

use function spl_object_id;

/**
 * This will represent a variable scope, similar to \Phan\Language\Scope.
 * Instead of tracking the union types for variable names, this will instead track definitions of variable names.
 */
class VariableTrackingScope
{
    /**
     * @var array<string,array<int,bool>>
     * Maps a variable id to a list of definitions in that scope.
     *
     * This is true if 100% of the definitions are made within the scope,
     * false if a fraction of the definitions could be from preceding scopes.
     */
    protected $defs = [];

    /**
     * @var array<string,int>
     * Maps a variable id to a list of uses which occurred before that scope begins.
     */
    protected $uses = [];

    public function recordDefinition(string $variable_name, Node $node)
    {
        // Create a new definition for variable_name.
        // Replace the definitions for $variable_name.
        $this->defs[$variable_name] = [spl_object_id($node) => true];
    }

    public function recordDefinitionById(string $variable_name, int $node_id)
    {
        // Create a new definition for variable_name.
        // Replace the definitions for $variable_name.
        $this->defs[$variable_name] = [$node_id => true];
    }

    /**
     * Overridden by subclasses
     * @return ?array<int,true>
     */
    public function getDefinition(string $variable_name)
    {
        return $this->defs[$variable_name] ?? null;
    }
}
