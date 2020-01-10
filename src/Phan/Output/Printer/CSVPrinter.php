<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use AssertionError;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This will print an issue as CSVs (Comma Separated Values) to the configured OutputInterface.
 */
final class CSVPrinter implements BufferedPrinterInterface
{

    /** @var OutputInterface for writing comma separated values to */
    private $output;

    /** @var resource in-memory stream for fputcsv() */
    private $stream;

    public function print(IssueInstance $instance): void
    {
        \fputcsv($this->stream, [
            $instance->getDisplayedFile(),
            $instance->getLine(),
            $instance->getIssue()->getSeverity(),
            $instance->getIssue()->getSeverityName(),
            Issue::getNameForCategory($instance->getIssue()->getCategory()),
            $instance->getIssue()->getType(),
            $instance->getMessageAndMaybeSuggestion(),
        ]);
    }

    /** flush printer buffer */
    public function flush(): void
    {
        \fseek($this->stream, 0);
        $contents = \stream_get_contents($this->stream);
        if (!\is_string($contents)) {
            throw new AssertionError("Failed to read in-memory csv stream");
        }
        $this->output->write($contents);
        \fclose($this->stream);
        $this->initStream();
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
        $this->initStream();
    }

    private function initStream(): void
    {
        // Because fputcsv works on file pointers we need to do a bit
        // of dancing around with a memory stream.
        $stream = \fopen("php://memory", "rw");
        if (!\is_resource($stream)) {
            throw new AssertionError('php://memory should always be openable');
        }
        $this->stream = $stream;
        \fputcsv($this->stream, [
            "filename", "line", "severity_ord", "severity_name",
            "category", "check_name", "message"
        ]);
    }
}
