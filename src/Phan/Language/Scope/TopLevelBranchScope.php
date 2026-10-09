<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

/**
 * A BranchScope layered directly on top of the GlobalScope, used between statements of the global scope.
 *
 * After a compound statement (loop, if, switch, try, etc.) in the global scope, BlockAnalysisVisitor folds
 * ordinary variables back into the GlobalScope so that `global $x` in functions sees the same variable objects.
 * GlobalScope::addVariable() deliberately ignores superglobals and configured hardcoded globals,
 * so branch-local refinements of those (e.g. after `if (!isset($_GET['x'])) { exit; }`) are kept in this scope instead.
 *
 * This is a marker class: it behaves exactly like BranchScope.
 */
final class TopLevelBranchScope extends BranchScope
{
}
