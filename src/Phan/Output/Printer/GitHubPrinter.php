<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Config;
use Phan\IssueInstance;
use Phan\Library\StringUtil;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/**
 * Outputs `IssueInstance`s in the GitHub error format to the configured OutputInterface
 *
 * @see https://help.github.com/en/actions/reference/workflow-commands-for-github-actions#setting-an-error-message Documentation about the GitHub output format
 */
final class GitHubPrinter implements IssuePrinterInterface
{

    /** @var OutputInterface an output that GitHub formatted issues can be written to. */
    protected $output;

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

        $issue = \sprintf(
            '::error file=%s,line=%d,col=0::%s %s',
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

        $this->output->writeln($issue);
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
