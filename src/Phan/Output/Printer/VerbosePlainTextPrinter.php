<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use Exception;
use Phan\Config;
use Phan\IssueInstance;
use Phan\Library\FileCache;
use Phan\Output\Colorizing;
use Symfony\Component\Console\Output\OutputInterface;

use function rtrim;
use function strlen;
use function trim;

/**
 * Outputs `IssueInstance`s to the provided OutputInterface in plain text format,
 * in addition to context about the issue.
 *
 * 'verbose' and 'text' are the only output formats for which the option `--color` is recommended.
 */
final class VerbosePlainTextPrinter extends PlainTextPrinter
{

    public function print(IssueInstance $instance): void
    {
        parent::print($instance);
        $absolute_path = Config::projectPath($instance->getFile());
        try {
            $entry = FileCache::getOrReadEntry($absolute_path);
        } catch (Exception $_) {
            return;
        }
        $lines = $entry->getLines();
        $line = $lines[$instance->getLine()] ?? '';
        if ($line === null || strlen($line) > Config::getValue('max_verbose_snippet_length') || trim($line) === '') {
            return;
        }

        $prefix = '>';
        if (Config::getValue('color_issue_messages')) {
            $prefix = Colorizing::colorizeTemplate('{SUGGESTION}', [$prefix]);
        }
        $this->output->writeln($prefix . ' ' . rtrim($line));
        $this->output->writeln('');
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
