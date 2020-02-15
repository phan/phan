<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\IssueInstance;

/**
 * SubscribeEmitIssueCapability is used when you want to see which issues phan has reported.
 */
interface SubscribeEmitIssueCapability
{
    /**
     * This method is called before Phan emits an (unsuppressed) issue.
     *
     * @return bool true if the issue should be suppressed.
     *              Most plugins should use SuppressionCapability instead,
     *              so that more generic issues can be used to suppress specific issues,
     *              and to avoid interfering with baselines.
     */
    public function onEmitIssue(IssueInstance $issue_instance): bool;
}
