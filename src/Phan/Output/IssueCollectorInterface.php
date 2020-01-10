<?php

declare(strict_types=1);

namespace Phan\Output;

use Phan\IssueInstance;

/**
 * Abstraction of functionality used to report and read issues to output.
 *
 * Multiple implementations are permitted for the language server protocol, unit testing, etc.
 */
interface IssueCollectorInterface
{

    /**
     * Collect issue
     * @param IssueInstance $issue
     */
    public function collectIssue(IssueInstance $issue): void;

    /**
     * @return list<IssueInstance> the list of collected issues from calls to collectIssue()
     */
    public function getCollectedIssues(): array;

    /**
     * Remove all collected issues (from the parse phase) for the given file paths.
     * Called from daemon mode.
     *
     * @param list<string> $files - the relative paths to those files
     */
    public function removeIssuesForFiles(array $files): void;

    /**
     * Remove all collected issues.
     */
    public function reset(): void;
}
