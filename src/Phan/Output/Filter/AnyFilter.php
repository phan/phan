<?php

namespace Phan\Output\Filter;

use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

final class AnyFilter implements IssueFilterInterface
{

    /**
     * @param IssueInstance $issue
     * @return bool
     */
    public function supports(IssueInstance $issue):bool
    {
        return true;
    }
}
