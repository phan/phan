<?php declare(strict_types=1);

namespace Phan\Output;

use Phan\IssueInstance;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstraction for printing (or recording) `IssueInstance`s in various ways.
 */
interface IssuePrinterInterface
{

    /**
     * Emit an issue to the configured OutputInterface
     *
     * @param IssueInstance $instance
     */
    public function print(IssueInstance $instance) : void;

    /**
     * Sets the OutputInterface instance that issues will be printed to.
     *
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output) : void;
}
