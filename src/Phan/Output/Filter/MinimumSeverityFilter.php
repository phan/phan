<?php declare(strict_types=1);
namespace Phan\Output\Filter;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

final class MinimumSeverityFilter implements IssueFilterInterface
{

    /** @var int */
    private $minimumSeverity;

    /**
     * MinimumSeverityFilter constructor.
     * @param $minimumSeverity
     */
    public function __construct(int $minimumSeverity = Issue::SEVERITY_LOW)
    {
        $this->minimumSeverity = $minimumSeverity;
    }


    /**
     * @param IssueInstance $issue
     * @return bool
     */
    public function supports(IssueInstance $issue):bool
    {
        return $issue->getIssue()->getSeverity() >= $this->minimumSeverity;
    }
}
