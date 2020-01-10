<?php

declare(strict_types=1);

namespace Phan\Output;

use Phan\IssueInstance;

/**
 * Instances of this are used to filter emitted issues down to issues that should be reported (based on configuration, etc).
 */
interface IssueFilterInterface
{
    /**
     * @param IssueInstance $issue
     * @return bool true if the issue should be reported
     */
    public function supports(IssueInstance $issue): bool;
}
