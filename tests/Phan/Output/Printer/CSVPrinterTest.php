<?php declare(strict_types = 1);

namespace Phan\Tests\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\Printer\CSVPrinter;
use Phan\Tests\BaseTest;
use Symfony\Component\Console\Output\BufferedOutput;

class CSVPrinterTest extends BaseTest
{

    public function testHeaderCorrespondsToData()
    {
        $output = new BufferedOutput();

        $printer = new CSVPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 0, ["foo"]));
        $printer->flush();

        $lines = array_map("str_getcsv", explode("\n", $output->fetch()));
        $fields = array_combine($lines[0], $lines[1]);
        $this->assertEquals("test.php", $fields["filename"]);
        $this->assertEquals(0, $fields["line"]);
        $this->assertEquals(10, $fields["severity_ord"]);
        $this->assertEquals("critical", $fields["severity_name"]);
        $this->assertEquals("Syntax", $fields["category"]);
        $this->assertEquals("PhanSyntaxError", $fields["check_name"]);
        $this->assertEquals("foo", $fields["message"]);
    }

    /**
     * @param string $string String to check against
     * @param string $messageExpected Message component of expected CSV line
     *
     * @dataProvider specialCharacterCasesProvider
     */
    public function testSpecialCharactersAreProperlyEncoded($string, $messageExpected)
    {
        $output = new BufferedOutput();

        $printer = new CSVPrinter();
        $printer->configureOutput($output);
        $printer->print(new IssueInstance(Issue::fromType(Issue::SyntaxError), 'test.php', 0, [$string]));
        $printer->flush();

        $expected = 'test.php,0,10,critical,Syntax,PhanSyntaxError,' . $messageExpected;
        $actual = explode("\n", $output->fetch())[1]; // Ignore header
        $this->assertEquals($expected, $actual);
    }

    public function specialCharacterCasesProvider()
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
