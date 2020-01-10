<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Closure;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;

/**
 * AutomaticFixCapability is used when you want to support --automatic-fix
 * for issue types emitted by the plugin (or other issue types)
 */
interface AutomaticFixCapability
{
    /**
     * This method is called to fetch the issue names the plugin can sometimes automatically fix.
     * Returns a map from issue name to the closure to generate a fix for instances of that issue.
     *
     * @return array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
     */
    public function getAutomaticFixers(): array;
}
