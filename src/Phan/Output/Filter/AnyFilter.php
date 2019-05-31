<?php declare(strict_types=1);

namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

/**
 * This is a filter which permits any IssueInstance to be output.
 */
final class AnyFilter implements IssueFilterInterface
{

    /**
     * @param IssueInstance $issue (@phan-unused-param)
     */
    public function supports(IssueInstance $issue):bool
    {
        return true;
    }
}
