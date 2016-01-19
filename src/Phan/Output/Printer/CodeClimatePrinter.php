<?php declare(strict_types = 1);

namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CodeClimatePrinter implements IssuePrinterInterface
{
    const CODECLIMATE_SEVERITY_INFO = 'info';
    const CODECLIMATE_SEVERITY_CRITICAL = 'critical';
    const CODECLIMATE_SEVERITY_NORMAL = 'normal';

    /** @var  OutputInterface */
    private $output;

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
        $issue = json_encode([
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
            ], JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE) . chr(0);

        $this->output->write($issue);
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
}
