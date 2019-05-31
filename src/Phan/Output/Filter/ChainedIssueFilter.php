<?php declare(strict_types=1);

namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

/**
 * ChainedIssueFilter is a combination of 0 or more filters.
 * It will reject an IssueInstance if any of the filters in the list reject that IssueInstance
 */
final class ChainedIssueFilter implements IssueFilterInterface
{

    /**
     * 0 or more filters. If any of these reject an IssueInstance,
     * then this ChainedIssueFilter will reject the instance.
     * @var IssueFilterInterface[]
     */
    private $filters = [];

    /**
     * ChainedIssueFilter constructor.
     *
     * @param IssueFilterInterface[] $filters
     */
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function supports(IssueInstance $issue):bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->supports($issue)) {
                return false;
            }
        }

        return true;
    }
}
