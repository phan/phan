<?php declare(strict_types = 1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\CheckstylePrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

class CheckstylePrinterTest extends BaseTest
{

    /**
     * @param string $string String to check against
     *
     * @dataProvider invalidUTF8StringsProvider
     */
    public function testUTF8CharactersDoNotCauseDOMAttrToFail($string)
    {
        $output = new BufferedOutput();

        $printer = new CheckstylePrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 0, [$string]));
        $printer->flush();
        $this->assertContains('PhanSyntaxError', $output->fetch());
    }

    public function invalidUTF8StringsProvider()
    {
        return [
            // Valid ASCII
            ["a"],
            // Valid 2 Octet Sequence
            ["\xc3\xb1"],
            // Invalid 2 Octet Sequence
            ["\xc3\x28"],
            // Invalid Sequence Identifier
            ["\xa0\xa1"],
            // Valid 3 Octet Sequence
            ["\xe2\x82\xa1"],
            // Invalid 3 Octet Sequence (in 2nd Octet)
            ["\xe2\x28\xa1"],
            // Invalid 3 Octet Sequence (in 3rd Octet)
            ["\xe2\x82\x28"],
            // Valid 4 Octet Sequence
            ["\xf0\x90\x8c\xbc"],
            // Invalid 4 Octet Sequence (in 2nd Octet)
            ["\xf0\x28\x8c\xbc"],
            // Invalid 4 Octet Sequence (in 3rd Octet)
            ["\xf0\x90\x28\xbc"],
            // Invalid 4 Octet Sequence (in 4th Octet)
            ["\xf0\x28\x8c\x28"],
            // Valid 5 Octet Sequence (but not Unicode!)
            ["\xf8\xa1\xa1\xa1\xa1"],
            // Valid 6 Octet Sequence (but not Unicode!)
            ["\xfc\xa1\xa1\xa1\xa1\xa1"],
        ];
    }
}
