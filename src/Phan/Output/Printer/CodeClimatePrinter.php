<?php declare(strict_types=1);

namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Library\StringUtil;
use Phan\Output\BufferedPrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This prints issues in the CodeClimate zero byte separated JSON format to the configured OutputInterface.
 */
final class CodeClimatePrinter implements BufferedPrinterInterface
{

    const CODECLIMATE_SEVERITY_INFO = 'info';
    const CODECLIMATE_SEVERITY_CRITICAL = 'critical';
    const CODECLIMATE_SEVERITY_NORMAL = 'normal';

    /** @var OutputInterface an output that zero byte separated JSON can be written to.  */
    private $output;

    /** @var array<int,array> a list of associative arrays with codeclimate issue fields. */
    private $messages = [];

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance)
    {
        $this->messages[] = [
            'type' => 'issue',
            'check_name' => $instance->getIssue()->getType(),
            'description' => $instance->getMessageAndMaybeSuggestion(),
            'categories' => ['Bug Risk'],
            'severity' => self::mapSeverity($instance->getIssue()->getSeverity()),
            'location' => [
                'path' => \preg_replace('/^\/code\//', '', $instance->getFile()),
                'lines' => [
                    'begin' => $instance->getLine(),
                    'end' => $instance->getLine(),
                ],
            ],
        ];
    }

    /**
     * @param int $raw_severity
     * @return string
     */
    private static function mapSeverity(int $raw_severity) : string
    {
        $severity = self::CODECLIMATE_SEVERITY_INFO;
        switch ($raw_severity) {
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
            $encoded_message = StringUtil::jsonEncode($message) . "\0";
            $this->output->write($encoded_message, false, OutputInterface::OUTPUT_RAW);
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
