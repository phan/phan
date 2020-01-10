<?php

declare(strict_types=1);

namespace Phan\Output\Printer;

use InvalidArgumentException;
use Phan\IssueInstance;
use Phan\Output\BufferedPrinterInterface;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints `IssueInstance`s in the file_set to the configured OutputInterface.
 *
 * This is necessary for daemon mode/the language server,
 * to limit outputted issues to the ones currently open in the IDE in all IDEs,
 * no matter what format is used (JSON, pylint, etc)
 */
final class FilteringPrinter implements BufferedPrinterInterface
{
    /** @var array<string,true> a set of relative file paths */
    private $file_set = [];

    /** @var IssuePrinterInterface the wrapped printer */
    private $printer;

    private static function normalize(string $file): string
    {
        return \str_replace(\DIRECTORY_SEPARATOR, "//", $file);
    }

    /** @param non-empty-list<string> $files a non-empty list of relative file paths. */
    public function __construct(
        array $files,
        IssuePrinterInterface $printer
    ) {
        if (\count($files) === 0) {
            throw new InvalidArgumentException("FilteringPrinter expects 1 or more files");
        }
        foreach ($files as $file) {
            $this->file_set[self::normalize($file)] = true;
        }
        $this->printer = $printer;
    }

    /**
     * @param IssueInstance $instance
     * @override
     */
    public function print(IssueInstance $instance): void
    {
        $file = $instance->getDisplayedFile();
        if (!isset($this->file_set[self::normalize($file)])) {
            return;
        }
        $this->printer->print($instance);
    }

    /**
     * @param OutputInterface $output
     * @override
     */
    public function configureOutput(OutputInterface $output): void
    {
        $this->printer->configureOutput($output);
    }

    /**
     * @override
     */
    public function flush(): void
    {
        if ($this->printer instanceof BufferedPrinterInterface) {
            $this->printer->flush();
        }
    }
}
