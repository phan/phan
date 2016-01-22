<?php declare(strict_types=1);
namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IgnoredFilesFilterInterface;
use Phan\Output\IssueFilterInterface;

final class FileIssueFilter implements IssueFilterInterface
{

    /** @var IgnoredFilesFilterInterface */
    private $ignoredFilesFilter;

    /**
     * FileIssueFilter constructor.
     *
     * @param IgnoredFilesFilterInterface $ignoredFilesFilter
     */
    public function __construct(
        IgnoredFilesFilterInterface $ignoredFilesFilter
    ) {
        $this->ignoredFilesFilter = $ignoredFilesFilter;
    }

    /**
     * @param IssueInstance $issue
     * @return bool
     */
    public function supports(IssueInstance $issue):bool
    {
        return !$this->ignoredFilesFilter->isFilenameIgnored($issue->getFile());
    }
}
