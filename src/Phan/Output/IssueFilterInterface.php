<?php

namespace Phan\Output;

use Phan\IssueInstance;

interface IssueFilterInterface
{
    /**
     * @param IssueInstance $issue
     * @return bool
     */
    public function supports(IssueInstance $issue):bool;
}
