<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Config;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Library\StringUtil;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints `IssueInstance`s in the pylint error format to the configured OutputInterface
 */
final class PylintPrinter implements IssuePrinterInterface
{
    /** @var OutputInterface an output that pylint formatted issues can be written to. */
    private $output;

    public function print(IssueInstance $instance): void
    {
        $message = \sprintf(
            "%s: %s",
            $instance->getIssue()->getType(),
            $instance->getMessage()
        );
        $line = \sprintf(
            "%s:%d: [%s] %s",
            $instance->getDisplayedFile(),
            $instance->getLine(),
            self::getSeverityCode($instance),
            $message
        );
        $column  = $instance->getColumn();
        if ($column > 0 && !Config::getValue('hide_issue_column')) {
            $line .= " (at column $column)";
        }
        $suggestion = $instance->getSuggestionMessage();
        if (StringUtil::isNonZeroLengthString($suggestion)) {
            $line .= " ($suggestion)";
        }

        $this->output->writeln($line);
    }

    /**
     * Returns a severity code that can be parsed by programs parsing pylint output
     * (e.g. `"E17000"` for PhanSyntaxError)
     */
    public static function getSeverityCode(IssueInstance $instance): string
    {
        $issue = $instance->getIssue();
        $category_id = $issue->getTypeId();
        switch ($issue->getSeverity()) {
            case Issue::SEVERITY_LOW:
                return 'C' . $category_id;
            case Issue::SEVERITY_NORMAL:
                return 'W' . $category_id;
            case Issue::SEVERITY_CRITICAL:
                return 'E' . $category_id;
            default:
                // @phan-suppress-next-line PhanPluginRemoveDebugCall
                \fwrite(\STDERR, "Unrecognized severity for " . $instance . ": " . $issue->getSeverity() . " (expected 0, 5, or 10)\n");
                return 'E' . $category_id;
        }
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
