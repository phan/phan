<?php declare(strict_types=1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\PylintPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests of the PylintPrinter converting `IssueInstance`s to the expected pylint output
 */
final class PylintPrinterTest extends BaseTest
{

    public function testPrintOutput()
    {
        $output = new BufferedOutput();

        $printer = new PylintPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredVariableDim), 'dim.php', 10, ['varName']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 1, ['fake error']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredMethod), 'undefinedmethod.php', 1, ['\\Foo::bar']));
        $expected_output = '';
        $expected_output .= 'dim.php:10: [C11027] PhanUndeclaredVariableDim: Variable $varName was undeclared, but array fields are being added to it.' . PHP_EOL;
        $expected_output .= 'test.php:1: [E17000] PhanSyntaxError: fake error' . PHP_EOL;
        $expected_output .= 'undefinedmethod.php:1: [E11013] PhanUndeclaredMethod: Call to undeclared method \Foo::bar' . PHP_EOL;
        $this->assertSame($expected_output, $output->fetch());
    }
}
