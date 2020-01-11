<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use AssertionError;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Library\StringUtil;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This prints issues as raw JSON to the configured OutputInterface.
 * The output is intended for use by other programs (or processes)
 */
class JSONPrinter implements BufferedPrinterInterface
{

    /** @var OutputInterface an output that JSON encoded can be written to. */
    protected $output;

    /** @var list<array<string,mixed>> the issue data to be JSON encoded. */
    protected $messages = [];

    public function print(IssueInstance $instance): void
    {
        $issue = $instance->getIssue();
        $message = [
            'type' => 'issue',
            'type_id' => $issue->getTypeId(),
            'check_name' => $issue->getType(),
            'description' =>
                Issue::getNameForCategory($issue->getCategory()) . ' ' .
                $issue->getType() . ' ' .
                $instance->getMessage(),  // suggestion included separately
            'severity' => $issue->getSeverity(),
            'location' => [
                'path' => $instance->getDisplayedFile(),
                'lines' => [
                    'begin' => $instance->getLine(),
                    'end' => $instance->getLine(),
                ],
            ],
        ];
        if ($instance->getColumn() > 0) {
            $message['location']['lines']['begin_column'] = $instance->getColumn();
        }
        $suggestion = $instance->getSuggestionMessage();
        if (StringUtil::isNonZeroLengthString($suggestion)) {
            $message['suggestion'] = $suggestion;
        }
        $this->messages[] = $message;
    }

    /** flush printer buffer */
    public function flush(): void
    {
        // NOTE: Need to use OUTPUT_RAW for JSON.
        // Otherwise, error messages such as "...Unexpected << (T_SL)" don't get formatted properly (They get escaped into unparsable JSON)
        $encoded_message = \json_encode($this->messages, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!\is_string($encoded_message)) {
            throw new AssertionError("Failed to encode anything for what should be an array");
        }
        $this->output->write($encoded_message . "\n", false, OutputInterface::OUTPUT_RAW);
        $this->messages = [];
    }

    public function configureOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
