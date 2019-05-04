<?php declare(strict_types=1);

namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IgnoredFilesFilterInterface;
use Phan\Output\IssueFilterInterface;

/**
 * FileIssueFilter is a filter that will ignore `IssueInstance`s based on their file name.
 */
final class FileIssueFilter implements IssueFilterInterface
{

    /** @var IgnoredFilesFilterInterface used to check if issues in a file name should be ignored. */
    private $ignored_files_filter;

    /**
     * FileIssueFilter constructor.
     *
     * @param IgnoredFilesFilterInterface $ignored_files_filter
     */
    public function __construct(
        IgnoredFilesFilterInterface $ignored_files_filter
    ) {
        $this->ignored_files_filter = $ignored_files_filter;
    }

    public function supports(IssueInstance $issue):bool
    {
        return !$this->ignored_files_filter->isFilenameIgnored($issue->getFile());
    }
}
