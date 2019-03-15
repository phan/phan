<?php declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Config;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Colorizing;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Outputs `IssueInstance`s to the provided OutputInterface in plain text format.
 *
 * 'text' is the only output format for which the option `--color` is recommended.
 */
final class PlainTextPrinter implements IssuePrinterInterface
{

    /** @var OutputInterface an output that plaintext formatted issues can be written to. */
    private $output;

    /**
     * @param IssueInstance $instance
     * @return void
     */
    public function print(IssueInstance $instance)
    {
        $file    = $instance->getFile();
        $line    = $instance->getLine();
        $issue   = $instance->getIssue();
        $type    = $issue->getType();
        $message = $instance->getMessage();
        $suggestion_message = $instance->getSuggestionMessage();

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
            if ($suggestion_message) {
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
            if ($suggestion_message) {
                $issue .= " ($suggestion_message)";
            }
        }

        $this->output->writeln($issue);
    }

    /**
     * @param OutputInterface $output
     * @return void
     */
    public function configureOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
