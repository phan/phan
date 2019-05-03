<?php declare(strict_types=1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\CSVPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests of CSVPrinter converting `IssueInstance`s to CSV text
 */
final class CSVPrinterTest extends BaseTest
{

    public function testHeaderCorrespondsToData() : void
    {
        $output = new BufferedOutput();

        $printer = new CSVPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 0, ["foo"]));
        $printer->flush();

        $lines = \array_map("str_getcsv", \explode("\n", $output->fetch()));
        $fields = \array_combine($lines[0], $lines[1]);
        $this->assertSame("test.php", $fields["filename"]);
        $this->assertSame("0", $fields["line"]);
        $this->assertSame("10", $fields["severity_ord"]);
        $this->assertSame("critical", $fields["severity_name"]);
        $this->assertSame("Syntax", $fields["category"]);
        $this->assertSame("PhanSyntaxError", $fields["check_name"]);
        $this->assertSame("foo", $fields["message"]);
    }

    /**
     * @param string $string String to check against
     * @param string $expected_message Message component of expected CSV line
     *
     * @dataProvider specialCharacterCasesProvider
     */
    public function testSpecialCharactersAreProperlyEncoded($string, $expected_message) : void
    {
        $output = new BufferedOutput();

        $printer = new CSVPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 0, [$string]));
        $printer->flush();

        $expected = 'test.php,0,10,critical,Syntax,PhanSyntaxError,' . $expected_message;
        $actual = \explode("\n", $output->fetch())[1]; // Ignore header
        $this->assertSame($expected, $actual);
    }

    /** @return array<int,array> */
    public function specialCharacterCasesProvider() : array
    {
        return [
            // Valid ASCII
            ["a", 'a'],
            // Comma's require extra quotes
            ["a,b", '"a,b"'],
            // Double quotes must be doubled
            ["a\"b", '"a""b"'],
        ];
    }
}
