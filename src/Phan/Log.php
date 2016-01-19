<?php
declare(strict_types=1);
namespace Phan;

use Phan\Output\IssueCollectorInterface;
use Phan\Output\IssuePrinterInterface;
use Phan\Output\PrinterFactory;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class Log {
	private static $instance;
	private $output_mode  = 'text';
	private $output_filename;
	private $output_mask = -1;

    /**
     * @var string[]
     */
    protected $msgs = [];

    /**
     * @var IssueInstance[]
     */
    protected $issues = [];

	protected function __construct() {
		$this->msgs = [];
	}

	public static function getInstance() : Log {
		if(null === self::$instance) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	public static function setOutputMode(string $mode) {
		$log = self::getInstance();
		$log->output_mode = $mode;
	}

	public static function setFilename(string $filename) {
		$log = self::getInstance();
		$log->output_filename = $filename;
	}

	public static function getOutputMask():int {
		$log = self::getInstance();
		return $log->output_mask;
	}

	public static function setOutputMask(int $mask) {
		$log = self::getInstance();
		$log->output_mask = $mask;
	}

    /** @var  IssuePrinterInterface */
    private static $printer;
    /** @var  PrinterFactory */
    private static $printerFactory;

    private static function getPrinter()
    {
        if (null === self::$printer) {

            $output = new ConsoleOutput();

            $log = Log::getInstance();
            if (null !== $log->output_filename) {
                $output = new StreamOutput(fopen($log->output_filename, 'w'));
            } else {
                if (Config::get()->progress_bar) {
                    fwrite(STDERR, "\n");
                }
            }

            self::$printer = self::getPrinterFactory()->getPrinter($log->output_mode, $output);
        }

        return self::$printer;
    }

    /**
     * @param IssueCollectorInterface $collector
     */
	public static function display(IssueCollectorInterface $collector) {
        foreach ($collector->getCollectedIssues() as $issue) {
            self::getPrinter()->print($issue);
        }
    }

    public static function setPrinter(IssuePrinterInterface $printer)
    {
        self::$printer = $printer;
    }

    public static function setPrinterFactory(PrinterFactory $factory)
    {
        self::$printerFactory = $factory;
    }

    private static function getPrinterFactory():PrinterFactory {
        if (null === self::$printerFactory) {
            self::$printerFactory = new PrinterFactory();
        }
        return self::$printerFactory;
    }
}
