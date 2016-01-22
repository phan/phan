<?php declare(strict_types = 1);
namespace Phan\Output;

use Phan\IssueInstance;

interface IssueCollectorInterface
{

    /**
     * Collect issue
     * @param IssueInstance $issue
     */
    public function collectIssue(IssueInstance $issue);

    /**
     * @return IssueInstance[]
     */
    public function getCollectedIssues():array;
}
