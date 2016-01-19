<?php declare(strict_types = 1);

namespace Phan\Output;

use Phan\IssueInstance;

interface IssuePrinterInterface
{
    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance);
}
