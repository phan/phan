<?php declare(strict_types = 1);
namespace Phan\Output;

use Phan\Output\Printer\JSONPrinter;
use Phan\Output\Printer\CodeClimatePrinter;
use Phan\Output\Printer\PlainTextPrinter;
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
        return ['text', 'json', 'codeclimate'];
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
            case 'text':
            default:
                $printer = new PlainTextPrinter();
                break;
        }

        $printer->configureOutput($output);

        return $printer;
    }
}
