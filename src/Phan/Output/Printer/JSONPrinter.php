<?php declare(strict_types = 1);
namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class JSONPrinter implements BufferedPrinterInterface
{

    /** @var  OutputInterface */
    private $output;

    /** @var array */
    private $messages = [];

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance)
    {
        $this->messages[] = [
            'type' => 'issue',
            'check_name' => $instance->getIssue()->getType(),
            'description' =>
                Issue::getNameForCategory($instance->getIssue()->getCategory()) . ' ' .
                $instance->getIssue()->getType() . ' ' .
                $instance->getMessage(),
            'severity' => $instance->getIssue()->getSeverity(),
            'location' => [
                'path' => preg_replace('/^\/code\//', '', $instance->getFile()),
                'lines' => [
                    'begin' => $instance->getLine(),
                    'end' => $instance->getLine(),
                ],
            ],
        ];
    }

    /** flush printer buffer */
    public function flush()
    {
        // NOTE: Need to use OUTPUT_RAW for JSON.
        // Otherwise, error messages such as "...Unexpected << (T_SL)" don't get formatted properly (They get escaped into unparseable JSON)
        $encodedMessage = json_encode($this->messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->output->write($encodedMessage, false, OutputInterface::OUTPUT_RAW);
        $this->messages = [];
    }

    /**
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
