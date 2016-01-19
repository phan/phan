<?php declare(strict_types = 1);

namespace Phan\Output\Collector;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\IgnoredFilesFilterInterface;
use Phan\Output\IssueCollectorInterface;

final class BufferingCollector implements IssueCollectorInterface
{
    /** @var  IssueInstance[] */
    private $issues = [];
    /** @var  int */
    private $minimumSeverity;
    /** @var  IgnoredFilesFilterInterface */
    private $ignoredFilesFilter;
    /** @var int */
    private $outputMask;

    /**
     * BufferingCollector constructor.
     * @param IgnoredFilesFilterInterface $ignoredFilesFilter
     * @param int $minimumSeverity
     * @param int $outputMask
     */
    public function __construct(
        IgnoredFilesFilterInterface $ignoredFilesFilter,
        int $minimumSeverity = Issue::SEVERITY_LOW,
        int $outputMask = -1
    )
    {
        $this->ignoredFilesFilter = $ignoredFilesFilter;
        $this->minimumSeverity = $minimumSeverity;
        $this->outputMask = $outputMask;
    }

    /**
     * Collect issue
     * @param IssueInstance $issue
     */
    public function collectIssue(IssueInstance $issue)
    {
        // Don't report anything for excluded files
        if ($this->ignoredFilesFilter->isFilenameIgnored($issue->getFile())) {
            return;
        }

        // Don't report anything below our minimum severity threshold
        if ($issue->getIssue()->getSeverity() < $this->minimumSeverity) {
            return;
        }

        if (!$this->filterCategory($issue)) {
            return;
        }

        $this->issues[$this->formatSortableKey($issue)] = $issue;
    }

    /**
     * @return IssueInstance[]
     */
    public function getCollectedIssues():array
    {
        ksort($this->issues);

        return $this->issues;
    }

    /**
     * @param IssueInstance $issue
     * @return int
     */
    public function filterCategory(IssueInstance $issue)
    {
        return $issue->getIssue()->getCategory() & $this->outputMask;
    }

    /**
     * @param IssueInstance $issue
     * @return string
     */
    public function formatSortableKey(IssueInstance $issue)
    {
        // This needs to be a sortable key so that output
        // is in the expected order
        return implode('|', [
            $issue->getFile(),
            str_pad((string)$issue->getLine(), 5, '0', STR_PAD_LEFT),
            $issue->getIssue()->getType(),
            $issue->getMessage()
        ]);
    }
}
