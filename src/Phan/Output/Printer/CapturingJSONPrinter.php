<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Library\StringUtil;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This prints issues as raw JSON to the configured OutputInterface.
 * The output is intended for use by other programs (or processes)
 */
class CapturingJSONPrinter extends JSONPrinter
{
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
        // Deliberately a no-op
    }

    public function configureOutput(OutputInterface $_): void
    {
        // Deliberately a no-op.
    }

    /** @return list<array<string,mixed>> the issue data to be JSON encoded. */
    public function getIssues(): array
    {
        return $this->messages;
    }
}
