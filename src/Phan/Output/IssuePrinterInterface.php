<?php declare(strict_types = 1);
namespace Phan\Output;

use Phan\IssueInstance;
use Symfony\Component\Console\Output\OutputInterface;

interface IssuePrinterInterface
{

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance);

    /**
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output);
}
