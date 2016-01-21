<?php

namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

final class CategoryIssueFilter implements IssueFilterInterface
{
    /** @var  int */
    private $mask;

    /**
     * CategoryIssueFilter constructor.
     * @param int $mask
     */
    public function __construct(int $mask = -1)
    {
        $this->mask = $mask;
    }

    /**
     * @param IssueInstance $issue
     * @return bool
     */
    public function supports(IssueInstance $issue) : bool
    {
        return (bool)($issue->getIssue()->getCategory() & $this->mask);
    }
}
