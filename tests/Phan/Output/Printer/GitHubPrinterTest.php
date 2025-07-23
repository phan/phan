<?php

declare(strict_types=1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\GitHubPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests of the GitHubPrinter converting `IssueInstance`s to the expected GitHub output
 */
final class GitHubPrinterTest extends BaseTest
{

    public function testPrintOutput(): void
    {
        $output = new BufferedOutput();

        $printer = new GitHubPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredVariableDim), 'dim.php', 10, ['varName']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 1, ['fake error']));
        $printer->print(new IssueInstance(Issue::fromType(Issue::UndeclaredMethod), 'undefinedmethod.php', 1, ['\\Foo::bar']));
        $expected_output = '';
        $expected_output .= '::error file=dim.php,line=10,col=0::PhanUndeclaredVariableDim Variable $varName was undeclared, but array fields are being added to it.' . \PHP_EOL;
        $expected_output .= '::error file=test.php,line=1,col=0::PhanSyntaxError fake error' . \PHP_EOL;
        $expected_output .= '::error file=undefinedmethod.php,line=1,col=0::PhanUndeclaredMethod Call to undeclared method \Foo::bar' . \PHP_EOL;
        $this->assertSame($expected_output, $output->fetch());
    }
}
