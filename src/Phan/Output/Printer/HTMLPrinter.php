<?php declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Config;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\HTML;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints `IssueInstance`s in a raw HTML format.
 * CSS can be specified elsewhere.
 *
 * @see internal/dump_html_styles for a way to generate CSS styles for this html
 */
final class HTMLPrinter implements IssuePrinterInterface
{
    /** @var OutputInterface an output that pylint formatted issues can be written to. */
    private $output;

    public function print(IssueInstance $instance) : void
    {
        $issue = $instance->getIssue();
        $message = HTML::htmlTemplate(
            $issue->getTemplateRaw(),
            $instance->getTemplateParameters()
        );
        $prefix = HTML::htmlTemplate(
            "{FILE}:{LINE}",
            [$instance->getDisplayedFile(), $instance->getLine()]
        );
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
        $issue_type = HTML::htmlTemplate($issue_type_template, [$issue->getType()]);
        $inner_html = "$prefix: $issue_type $message";
        $column = $instance->getColumn();
        if ($column > 0 && !Config::getValue('hide_issue_column')) {
            $inner_html .= HTML::htmlTemplate(" ({DETAILS})", ["at column $column"]);
        }
        $suggestion = $instance->getSuggestionMessage();
        if ($suggestion) {
            $inner_html .= HTML::htmlTemplate(" ({SUGGESTION})", [$suggestion]);
        }

        $this->output->writeln("<p>$inner_html</p>");
    }

    public function configureOutput(OutputInterface $output) : void
    {
        $this->output = $output;
    }
}
