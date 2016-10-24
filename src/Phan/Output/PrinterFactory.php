<?php declare(strict_types = 1);
namespace Phan\Output;

use Phan\Output\Printer\CheckstylePrinter;
use Phan\Output\Printer\CodeClimatePrinter;
use Phan\Output\Printer\CSVPrinter;
use Phan\Output\Printer\JSONPrinter;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Output\Printer\PylintPrinter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PrinterFactory
 * Subject of future refactoring to be a bit more extensible
 */
class PrinterFactory
{

    /**
     * @return string[]
     */
    public function getTypes():array
    {
        return ['text', 'json', 'csv', 'codeclimate', 'checkstyle', 'pylint'];
    }

    public function getPrinter($type, OutputInterface $output):IssuePrinterInterface
    {
        switch ($type) {
            case 'codeclimate':
                $printer = new CodeClimatePrinter();
                break;
            case 'json':
                $printer = new JSONPrinter();
                break;
            case 'checkstyle':
                $printer = new CheckstylePrinter();
                break;
            case 'csv':
                $printer = new CSVPrinter();
                break;
            case 'pylint':
                $printer = new PylintPrinter();
                break;
            case 'text':
            default:
                $printer = new PlainTextPrinter();
                break;
        }

        $printer->configureOutput($output);

        return $printer;
    }
}
