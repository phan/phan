<?php declare(strict_types = 1);
namespace Phan\Output\Printer;

use Phan\IssueInstance;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PlainTextPrinter implements IssuePrinterInterface {

    /** @var OutputInterface */
    private $output;

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance) {
        $issue = sprintf(
            '%s:%d %s %s',
            $instance->getFile(),
            $instance->getLine(),
            $instance->getIssue()->getType(),
            $instance->getMessage()
        );

        $this->output->writeln($issue);
    }

    /**
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output) {
        $this->output = $output;
    }
}
