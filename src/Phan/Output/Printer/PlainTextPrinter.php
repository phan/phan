<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Config;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Library\StringUtil;
use Phan\Output\Colorizing;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/**
 * Outputs `IssueInstance`s to the provided OutputInterface in plain text format.
 *
 * 'text' is the only output format for which the option `--color` is recommended.
 */
final class PlainTextPrinter implements IssuePrinterInterface
{

    /** @var OutputInterface an output that plaintext formatted issues can be written to. */
    private $output;

    public function print(IssueInstance $instance): void
    {
        $file    = $instance->getDisplayedFile();
        $line    = $instance->getLine();
        $issue   = $instance->getIssue();
        $type    = $issue->getType();
        $message = $instance->getMessage();
        $suggestion_message = $instance->getSuggestionMessage();
        $column  = $instance->getColumn();
        if ($column > 0 && !Config::getValue('hide_issue_column')) {
            $column_message = "at column $column";
        } else {
            $column_message = null;
        }

        if (Config::getValue('color_issue_messages')) {
            switch ($issue->getSeverity()) {
                case Issue::SEVERITY_CRITICAL:
                    $issue_type_template = '{ISSUETYPE_CRITICAL}';
                    break;
                case Issue::SEVERITY_NORMAL:
                    $issue_type_template = '{ISSUETYPE_NORMAL}';
                    break;
                default:
                    $issue_type_template = '{ISSUETYPE}';
                    break;
            }
            $issue = Colorizing::colorizeTemplate("{FILE}:{LINE} $issue_type_template %s", [
                $file,
                $line,
                $type,
                $message
            ]);
            if (is_string($column_message)) {
                $issue .= Colorizing::colorizeTemplate(" ({DETAILS})", [$column_message]);
            }
            if (StringUtil::isNonZeroLengthString($suggestion_message)) {
                $issue .= Colorizing::colorizeTemplate(" ({SUGGESTION})", [$suggestion_message]);
            }
        } else {
            $issue = \sprintf(
                '%s:%d %s %s',
                $file,
                $line,
                $type,
                $message
            );
            if (is_string($column_message)) {
                $issue .= " ($column_message)";
            }
            if (StringUtil::isNonZeroLengthString($suggestion_message)) {
                $issue .= " ($suggestion_message)";
            }
        }

        $this->output->writeln($issue);
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
