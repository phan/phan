<?php declare(strict_types=1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\HTMLPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests of the HTMLPrinter converting `IssueInstance`s to the expected html fragment.
 */
final class HTMLPrinterTest extends BaseTest
{

    public function testPrintOutput() : void
    {
        $output = new BufferedOutput();

        $printer = new HTMLPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredVariableDim), 'dim.php', 10, ['varName']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 1, ['fake error']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredMethod), 'undefinedmethod.php', 1, ['\\Foo::bar']));
        $expected_output = '';
        $expected_output .= '<p><span class="phan_file">dim.php</span>:<span class="phan_line">10</span>: <span class="phan_issuetype">PhanUndeclaredVariableDim</span> Variable <span class="phan_variable">$varName</span> was undeclared, but array fields are being added to it.</p>' . \PHP_EOL;
        $expected_output .= '<p><span class="phan_file">test.php</span>:<span class="phan_line">1</span>: <span class="phan_issuetype_critical">PhanSyntaxError</span> <span class="phan_unknown">fake error</span></p>' . \PHP_EOL;
        $expected_output .= '<p><span class="phan_file">undefinedmethod.php</span>:<span class="phan_line">1</span>: <span class="phan_issuetype_critical">PhanUndeclaredMethod</span> Call to undeclared method <span class="phan_method">\Foo::bar</span></p>' . \PHP_EOL;
        $this->assertSame($expected_output, $output->fetch());
    }
}
