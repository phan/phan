<?php declare(strict_types=1);
namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

final class AnyFilter implements IssueFilterInterface
{

    /**
     * @param IssueInstance $issue (@phan-unused-param)
     * @return bool
     */
    public function supports(IssueInstance $issue):bool
    {
        return true;
    }
}
