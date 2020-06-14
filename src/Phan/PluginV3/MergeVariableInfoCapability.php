<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Closure;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;

/**
 * MergeVariableInfoCapability is used when you want to update some data of a given variable that is seen across several branches.
 * For instance, phan determines the union type of a variable based on the possible types found inside conditional branches.
 */
interface MergeVariableInfoCapability
{
    /**
     * @return Closure(Variable,Scope[],bool)
     * The closure will be called with a Variable object and a list of scopes that we're merging.
     * Closure Type: function(Variable $variable, array $scopes, bool $var_exists_in_all_branches) : void {...}
     */
    public function getMergeVariableInfoClosure(): Closure;
}
