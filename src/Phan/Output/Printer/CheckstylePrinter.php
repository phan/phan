<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\IssueInstance;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

use const ENT_NOQUOTES;

/**
 * This prints `IssueInstance`s in the checkstyle XML format to the configured OutputInterface
 * @phan-file-suppress PhanTypeArraySuspiciousNullable TODO: fix false positive inferred for positive values of $this->files
 */
final class CheckstylePrinter implements BufferedPrinterInterface
{

    /** @var OutputInterface an output that XML can be written to. */
    private $output;

    /** @var array<string,list<array<string,mixed>>> maps files with issues to the list of those issues */
    private $files = [];

    public function print(IssueInstance $instance): void
    {
        $file = $instance->getDisplayedFile();
        if (!isset($this->files[$file])) {
            $this->files[$file] = [];
        }

        // Group issues by file
        $this->files[$file][] = [
            'line' => $instance->getLine(),
            'source' => $instance->getIssue()->getType(),
            'message' => $instance->getMessageAndMaybeSuggestion(),
            'severity' => $instance->getIssue()->getSeverityName(),
        ];
    }

    /** flush printer buffer */
    public function flush(): void
    {
        $document = new \DOMDocument('1.0', 'ISO-8859-15');

        $checkstyle = new \DOMElement('checkstyle');
        $document->appendChild($checkstyle);
        $checkstyle->appendChild(new \DOMAttr('version', '6.5'));

        // Write each file to the DOM
        foreach ($this->files as $file_name => $error_list) {
            $file = new \DOMElement('file');
            $checkstyle->appendChild($file);
            $file->appendChild(new \DOMAttr('name', $file_name));

            // Write each error to the file
            foreach ($error_list as $error_map) {
                $error = new \DOMElement('error');
                $file->appendChild($error);

                // Write each element of the error as an attribute
                // of the error
                $error->appendChild(
                    new \DOMAttr('line', \htmlspecialchars((string)$error_map['line'], ENT_NOQUOTES, 'UTF-8'))
                );

                // Map phan severity to Jenkins/Checkstyle severity levels
                switch ($error_map['severity']) {
                    case 'low':
                        $level = 'info';
                        break;
                    case 'critical':
                        $level = 'error';
                        break;
                    case 'normal':
                    default:
                        $level = 'warning';
                        break;
                }

                $error->appendChild(
                    new \DOMAttr('severity', \htmlspecialchars($level, ENT_NOQUOTES, 'UTF-8'))
                );

                $error->appendChild(
                    new \DOMAttr('message', \htmlspecialchars((string)$error_map['message'], ENT_NOQUOTES, 'UTF-8'))
                );

                $error->appendChild(
                    new \DOMAttr('source', \htmlspecialchars((string)$error_map['source'], ENT_NOQUOTES, 'UTF-8'))
                );
            }
        }

        $document->formatOutput = true;
        $this->output->write($document->saveXML());
        $this->files = [];
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
