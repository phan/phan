<?php declare(strict_types=1);
namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

final class ChainedIssueFilter implements IssueFilterInterface
{

    /** @var IssueFilterInterface[] */
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

    /**
     * @param IssueInstance $issue
     * @return bool
     */
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
