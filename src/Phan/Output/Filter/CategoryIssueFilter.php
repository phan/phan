<?php
declare(strict_types=1);

namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

/**
 * This is a filter which limits `IssueInstance`s to specific categories,
 * represented as the bitmask $this->mask
 */
final class CategoryIssueFilter implements IssueFilterInterface
{
    /** @var int a bitmask of categories to allow */
    private $mask;

    /**
     * CategoryIssueFilter constructor.
     * @param int $mask
     */
    public function __construct(int $mask = -1)
    {
        $this->mask = $mask;
    }

    public function supports(IssueInstance $issue) : bool
    {
        return (bool)($issue->getIssue()->getCategory() & $this->mask);
    }
}
