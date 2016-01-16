<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\CodeBase;
use Phan\Language\Type;
use Phan\Phan;

class PhanTest extends \PHPUnit_Framework_TestCase implements CodeBaseAwareTestInterface
{
    /** @var  CodeBase */
    private $code_base;

    public function setCodeBase(CodeBase $codeBase = null)
    {
        $this->code_base = $codeBase;
    }

    public function getTestFiles()
    {
        $files = array_filter(
            array_filter(
                scandir(TEST_FILE_DIR),
                function ($filename) {
                    return !in_array($filename, ['.', '..'], true);
                }
            )
        );

        return array_combine(
            $files, array_map(
                function ($filename) {
                    return [$filename];
                }, $files
            )
        );
    }

    /**
     * This reads all files in `tests/files/src`, runs
     * the analyzer on each and compares the output
     * to the files's counterpart in
     * `tests/files/expected`
     *
     * @param string $test_file_name
     * @dataProvider getTestFiles
     */
    public function testFiles($test_file_name) {
            // Get the path to the test file
            $test_file_path =
                TEST_FILE_DIR . '/' . $test_file_name;

            // Get the path of the expected output file
            $expected_file_path =
                EXPECTED_DIR . '/' . $test_file_name . '.expected';

            $expected_output = '';
            if (is_file($expected_file_path)) {
                // Read the expected output
                $expected_output =
                    trim(file_get_contents($expected_file_path));
            }

            // Start reading everything sent to STDOUT
            // and compare it to the expected value once
            // the analzyer finishes running
            ob_start();

            try {
                // Run the analyzer
                (new Phan)->analyzeFileList(
                    clone($this->code_base),
                    [$test_file_path]
                );
            } catch (\Exception $exception) {
                // TODO: inexplicably bad things happen here
                // print "\n" . $exception->getMessage() . "\n";
            }

            $output = trim(ob_get_clean());

            // Uncomment to save the output back to the expected
            // output. This should be done for error message
            // text changes and only if you promise to be careful.
            /*
            $saved_output = $output;
            $saved_output = preg_replace('/[^ :\n]*\/'.$test_file_name.'/', '%s', $saved_output);
            $saved_output = preg_replace('/closure_[^\(]*\(/', 'closure_%s(', $saved_output);
            if (!empty($saved_output) && strlen($saved_output) > 0) {
                $saved_output .= "\n";
            }
            file_put_contents($expected_file_path, $saved_output);
            $expected_output =
                trim(file_get_contents($expected_file_path));
            */

            $wanted_re = preg_replace('/\r\n/', "\n", $expected_output);
			// do preg_quote, but miss out any %r delimited sections
            $temp = "";
            $r = "%r";
            $startOffset = 0;
            $length = strlen($wanted_re);
            while($startOffset < $length) {
                $start = strpos($wanted_re, $r, $startOffset);
                if ($start !== false) {
                    // we have found a start tag
                    $end = strpos($wanted_re, $r, $start+2);
                    if ($end === false) {
                        // unbalanced tag, ignore it.
                        $end = $start = $length;
                    }
                } else {
                    // no more %r sections
                    $start = $end = $length;
                }
                // quote a non re portion of the string
                $temp = $temp . preg_quote(substr($wanted_re, $startOffset, ($start - $startOffset)),  '/');
                // add the re unquoted.
                if ($end > $start) {
                    $temp = $temp . '(' . substr($wanted_re, $start+2, ($end - $start-2)). ')';
                }
                $startOffset = $end + 2;
            }
            $wanted_re = $temp;
            $wanted_re = str_replace(['%binary_string_optional%'], 'string', $wanted_re);
            $wanted_re = str_replace(['%unicode_string_optional%'], 'string', $wanted_re);
            $wanted_re = str_replace(['%unicode\|string%', '%string\|unicode%'], 'string', $wanted_re);
            $wanted_re = str_replace(['%u\|b%', '%b\|u%'], '', $wanted_re);
            // Stick to basics
            $wanted_re = str_replace('%e', '\\' . DIRECTORY_SEPARATOR, $wanted_re);
            $wanted_re = str_replace('%s', '[^\r\n]+', $wanted_re);
            $wanted_re = str_replace('%S', '[^\r\n]*', $wanted_re);
            $wanted_re = str_replace('%a', '.+', $wanted_re);
            $wanted_re = str_replace('%A', '.*', $wanted_re);
            $wanted_re = str_replace('%w', '\s*', $wanted_re);
            $wanted_re = str_replace('%i', '[+-]?\d+', $wanted_re);
            $wanted_re = str_replace('%d', '\d+', $wanted_re);
            $wanted_re = str_replace('%x', '[0-9a-fA-F]+', $wanted_re);
            $wanted_re = str_replace('%f', '[+-]?\.?\d+\.?\d*(?:[Ee][+-]?\d+)?', $wanted_re);
            $wanted_re = str_replace('%c', '.', $wanted_re);
            // %f allows two points "-.0.0" but that is the best *simple* expression

            $this->assertRegExp("/^$wanted_re\$/", $output, "Unexpected output in $test_file_path");
    }
}
