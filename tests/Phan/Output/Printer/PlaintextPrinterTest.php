<?php declare(strict_types = 1);

namespace Phan\Tests\Output\Printer;

use Phan\Config;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

class PlainTextPrinterTest extends BaseTest
{

    public function tearDown()
    {
        parent::tearDown();
        Config::setValue('color_issue_messages', false);
    }

    /**
     * Sanity check that the expected color codes are emitted.
     */
    public function testPrintColorizedOutput()
    {
        Config::setValue('color_issue_messages', true);
        $output = new BufferedOutput();

        $printer = new PlainTextPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredVariableDim), 'dim.php', 10, ['varName']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 1, ['fake error']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredMethod), 'undefinedmethod.php', 1, ['\\Foo::bar']));
        $expected_output = '';
        // phpcs:disable
        $expected_output .= "\x1b[96mdim.php\x1b[0m:\x1b[37m10\x1b[0m \x1b[93mPhanUndeclaredVariableDim\x1b[0m Variable $\x1b[96mvarName\x1b[0m was undeclared, but array fields are being added to it." . PHP_EOL;
        $expected_output .= "\x1b[96mtest.php\x1b[0m:\x1b[37m1\x1b[0m \x1b[31mPhanSyntaxError\x1b[0m fake error" . PHP_EOL;
        $expected_output .= "\x1b[96mundefinedmethod.php\x1b[0m:\x1b[37m1\x1b[0m \x1b[31mPhanUndeclaredMethod\x1b[0m Call to undeclared method \x1b[93m\\Foo::bar\x1b[0m" . PHP_EOL;
        // phpcs:enable
        $actual_output = $output->fetch();
        $this->assertSame(json_encode($expected_output), json_encode($actual_output));
        $this->assertSame($expected_output, $actual_output);
    }
}
