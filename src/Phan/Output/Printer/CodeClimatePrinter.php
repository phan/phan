<?php declare(strict_types = 1);
namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CodeClimatePrinter implements BufferedPrinterInterface
{

    const CODECLIMATE_SEVERITY_INFO = 'info';
    const CODECLIMATE_SEVERITY_CRITICAL = 'critical';
    const CODECLIMATE_SEVERITY_NORMAL = 'normal';

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
            'description' => $instance->getMessage(),
            'categories' => ['Bug Risk'],
            'severity' => self::mapSeverity($instance->getIssue()->getSeverity()),
            'location' => [
                'path' => preg_replace('/^\/code\//', '', $instance->getFile()),
                'lines' => [
                    'begin' => $instance->getLine(),
                    'end' => $instance->getLine(),
                ],
            ],
        ];
    }

    /**
     * @param int $rawSeverity
     * @return string
     */
    private static function mapSeverity(int $rawSeverity):string
    {
        $severity = self::CODECLIMATE_SEVERITY_INFO;
        switch ($rawSeverity) {
            case Issue::SEVERITY_CRITICAL:
                $severity = self::CODECLIMATE_SEVERITY_CRITICAL;
                break;
            case Issue::SEVERITY_NORMAL:
                $severity = self::CODECLIMATE_SEVERITY_NORMAL;
                break;
        }

        return $severity;
    }

    /** flush printer buffer */
    public function flush()
    {

        // See https://github.com/codeclimate/spec/blob/master/SPEC.md#output
        // for details on the CodeClimate output format
        foreach ($this->messages as $message) {
            $encodedMessage = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\0";
            $this->output->write($encodedMessage, false, OutputInterface::OUTPUT_RAW);
        }
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
