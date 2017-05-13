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

    /**
     * Remove all collected issues (from the parse phase) for the given file paths.
     * Called from daemon mode.
     *
     * @param string[] $files - the relative paths to those files
     * @return void
     */
    public function removeIssuesForFiles(array $files);

    /**
     * Remove all collected issues.
     */
    public function reset();
}
