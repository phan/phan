<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

/**
 * This will represent a variable scope, similar to \Phan\Language\Scope.
 * Instead of tracking the union types for variable names, this will instead track definitions of variable names.
 */
final class VariableTrackingScope
{
    /**
     * @var array<string,array<int,bool>>
     * Maps a variable id to a list of definitions in that scope.
     *
     * This is true if 100% of the definitions are made within the scope,
     * false if a fraction of the definitions could be from preceding scopes.
     */
    public $defs = [];

    /**
     * @var array<string,int>
     * Maps a variable id to a list of uses which occurred before that scope begins.
     */
    public $uses = [];
}
