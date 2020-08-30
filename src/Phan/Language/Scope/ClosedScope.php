<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\Scope;

/**
 * ClosedScope represents a scope that does not inherit variables from the parent scope
 */
class ClosedScope extends Scope
{
    /**
     * @unused-param $scope
     * Returns empty because this is expected to be the excluded scope or a clone of it.
     */
    public function getVariableMapExcludingScope(?Scope $scope): array
    {
        // Phan always generates a branch scope in front of the branch scope.
        // The global scope can have hundreds or thousands of variables in some projects, avoid merging variables from it.
        return [];
    }
}
