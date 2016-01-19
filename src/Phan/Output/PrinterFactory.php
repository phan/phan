<?php declare(strict_types = 1);

namespace Phan\Output;

use Phan\Output\Printer\CodeClimatePrinter;
use Phan\Output\Printer\PlainTextPrinter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PrinterFactory
 * Subject of future refactoring to be a bit more extensible
 */
class PrinterFactory
{
    public function getPrinter($type, OutputInterface $output)
    {
        switch ($type) {
            case 'codeclimate':
                $printer = new CodeClimatePrinter($output);
                break;
            case 'text':
            default:
                $printer = new PlainTextPrinter($output);
                break;
        }

        return $printer;
    }
}
