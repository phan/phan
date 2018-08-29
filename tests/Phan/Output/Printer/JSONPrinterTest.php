<?php declare(strict_types = 1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\JSONPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

final class JSONPrinterTest extends BaseTest
{

    public function testPrintOutput()
    {
        $output = new BufferedOutput();

        $printer = new JSONPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredVariableDim), 'dim.php', 10, ['varName']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 1, ['fake error']));
        $printer->flush();
        // phpcs:ignore
        $expected_output = '[{"type":"issue","type_id":11027,"check_name":"PhanUndeclaredVariableDim","description":"UndefError PhanUndeclaredVariableDim Variable $varName was undeclared, but array fields are being added to it.","severity":0,"location":{"path":"dim.php","lines":{"begin":10,"end":10}}},{"type":"issue","type_id":17000,"check_name":"PhanSyntaxError","description":"Syntax PhanSyntaxError fake error","severity":10,"location":{"path":"test.php","lines":{"begin":1,"end":1}}}]';
        $this->assertSame($expected_output, $output->fetch());
    }
}
