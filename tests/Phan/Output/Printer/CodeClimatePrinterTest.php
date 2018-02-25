<?php declare(strict_types = 1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\CodeClimatePrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

class CodeClimatePrinterTest extends BaseTest
{

    public function testPrintOutput()
    {
        $output = new BufferedOutput();

        $printer = new CodeClimatePrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredVariableDim), 'dim.php', 10, ['varName']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 1, ['fake error']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredMethod), 'undefinedmethod.php', 1, ['\\Foo::bar']));
        $printer->flush();

        $expected_output = '';
        $expected_output .= '{"type":"issue","check_name":"PhanUndeclaredVariableDim","description":"Variable $varName was undeclared, but array fields are being added to it.","categories":["Bug Risk"],"severity":"info","location":{"path":"dim.php","lines":{"begin":10,"end":10}}}' . "\x00";
        $expected_output .= '{"type":"issue","check_name":"PhanSyntaxError","description":"fake error","categories":["Bug Risk"],"severity":"critical","location":{"path":"test.php","lines":{"begin":1,"end":1}}}' . "\x00";
        $expected_output .= '{"type":"issue","check_name":"PhanUndeclaredMethod","description":"Call to undeclared method \\\\Foo::bar","categories":["Bug Risk"],"severity":"normal","location":{"path":"undefinedmethod.php","lines":{"begin":1,"end":1}}}' . "\x00";
        $this->assertSame($expected_output, $output->fetch());
    }
}
