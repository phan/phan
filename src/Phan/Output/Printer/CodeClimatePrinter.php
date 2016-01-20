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

    private $messages = [];

    /**
     * CodeClimateFormatter constructor.
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

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
            'categories' => ['Bug Risk'],
            'severity' => self::mapSeverety($instance->getIssue()->getSeverity()),
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
    private static function mapSeverety(int $rawSeverity):string
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
        $this->output->write(json_encode($this->messages, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE) . chr(0));
        $this->messages = [];
    }
}
