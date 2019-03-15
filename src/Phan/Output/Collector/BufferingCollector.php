<?php declare(strict_types=1);

namespace Phan\Output\Collector;

use Phan\IssueInstance;
use Phan\Output\Filter\AnyFilter;
use Phan\Output\IssueCollectorInterface;
use Phan\Output\IssueFilterInterface;

/**
 * BufferingCollector represents an issue collector that stores issues for use later.
 */
final class BufferingCollector implements IssueCollectorInterface
{

    /** @var array<string,IssueInstance> the issues that were collected */
    private $issues = [];

    /** @var IssueFilterInterface used to prevent some issues from being output (based on config and CLI options) */
    private $filter;

    /**
     * BufferingCollector constructor.
     * @param ?IssueFilterInterface $filter
     */
    public function __construct(IssueFilterInterface $filter = null)
    {
        $this->filter = $filter ?? (new AnyFilter());
    }

    /**
     * Collect issue
     * @param IssueInstance $issue
     */
    public function collectIssue(IssueInstance $issue)
    {
        if (!$this->filter->supports($issue)) {
            return;
        }

        $this->issues[$this->formatSortableKey($issue)] = $issue;
    }

    /**
     * @param IssueInstance $issue
     * @return string
     */
    private function formatSortableKey(IssueInstance $issue) : string
    {
        // This needs to be a sortable key so that output
        // is in the expected order
        return \implode('|', [
            $issue->getFile(),
            \str_pad((string)$issue->getLine(), 5, '0', \STR_PAD_LEFT),
            $issue->getIssue()->getType(),
            $issue->getMessage()
        ]);
    }

    /**
     * @return array<int,IssueInstance>
     */
    public function getCollectedIssues():array
    {
        \ksort($this->issues);
        return \array_values($this->issues);
    }

    /**
     * Clear the array of issues without outputting anything.
     *
     * Called after analysis ends.
     *
     * @return void
     */
    public function flush()
    {
        $this->issues = [];
    }

    /**
     * Remove all collected issues (from the parse phase) for the given file paths.
     * Called from daemon mode.
     *
     * @param array<int,string> $files - the relative paths to those files
     * @return void
     */
    public function removeIssuesForFiles(array $files)
    {
        $file_set = \array_flip($files);
        foreach ($this->issues as $key => $issue) {
            if (\array_key_exists($issue->getFile(), $file_set)) {
                unset($this->issues[$key]);
            }
        }
    }

    /**
     * Removes all collected issues.
     */
    public function reset()
    {
        $this->issues = [];
    }
}
