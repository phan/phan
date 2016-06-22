<?php declare(strict_types = 1);
namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CSVPrinter implements BufferedPrinterInterface
{

    /** @var  OutputInterface */
    private $output;

    /** @var resource */
    private $stream;

    /** @var array */
    private $messages = [];

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance)
    {
        fputcsv($this->stream, [
            $instance->getFile(),
            $instance->getLine(),
            $instance->getIssue()->getSeverity(),
            $instance->getIssue()->getSeverityName(),
            Issue::getNameForCategory($instance->getIssue()->getCategory()),
            $instance->getIssue()->getType(),
            $instance->getMessage(),
        ]);
    }

    /** flush printer buffer */
    public function flush()
    {
        fseek($this->stream, 0);
        $this->output->write(stream_get_contents($this->stream));
        fclose($this->stream);
        $this->initStream();
    }

    /**
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->initStream();
    }

    private function initStream()
    {
        // Because fputcsv works on file pointers we need to do a bit
        // of dancing around with a memory stream.
        $this->stream = fopen("php://memory", "rw");
        fputcsv($this->stream, [
            "filename", "line", "severity_ord", "severity_name",
            "category", "check_name", "message"
        ]);
    }
}
