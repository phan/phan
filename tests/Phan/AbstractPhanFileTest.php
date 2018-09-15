<?php declare(strict_types = 1);
namespace Phan\Tests;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Type;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Base class for tests that contain
 *
 * - a src/ folder with analyzed PHP files, and
 * - the expected/ folder of expected error (template) lines for the corresponding files.
 */
abstract class AbstractPhanFileTest extends BaseTest implements CodeBaseAwareTestInterface
{
    const EXPECTED_SUFFIX = '.expected';

    /** @var CodeBase */
    private $code_base;

    /**
     * @return void
     */
    public function setCodeBase(CodeBase $code_base = null)
    {
        $this->code_base = $code_base;
    }

    /**
     * @return string[][] Array of <filename => [filename]>
     */
    abstract public function getTestFiles();

    /**
     * @return void
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        // Reset the config file
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        // Clear the plugins
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        // Reset the config file
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        // Clear the plugins
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * Setup our state before running each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        Type::clearAllMemoizations();
    }

    /**
     * Reset any changes we made to our global state
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        Type::clearAllMemoizations();
    }

    /**
     * Placeholder for getTestFiles dataProvider
     *
     * @param string $source_dir
     * @return string[][]
     */
    protected function scanSourceFilesDir(string $source_dir, string $expected_dir)
    {
        // TODO: Make Phan know that array_filter with a single argument implies elements aren't falsey
        $files = array_filter(
            array_filter(
                scandir($source_dir),
                function (string $filename) : bool {
                    // Ignore directories and hidden files.
                    return !in_array($filename, ['.', '..'], true) && substr($filename, 0, 1) !== '.' && preg_match('@\.php$@', $filename);
                }
            )
        );

        // NOTE: To avoid ParseError in php-ast
        if (PHP_VERSION_ID < 70100) {
            $suffix = '70';
        } elseif (PHP_VERSION_ID < 70200) {
            $suffix = '71';
        } else {
            $suffix = '72';
        }

        return array_combine(
            $files,
            array_map(
                function (string $filename) use ($source_dir, $expected_dir, $suffix) : array {
                    return [
                        [self::getFileForPHPVersion($source_dir . DIRECTORY_SEPARATOR . $filename, $suffix)],
                        self::getFileForPHPVersion($expected_dir . DIRECTORY_SEPARATOR . $filename . self::EXPECTED_SUFFIX, $suffix),
                    ];
                },
                $files
            )
        );
    }

    protected function getFileForPHPVersion(string $path, string $suffix) : string
    {
        $suffix_path = $path . $suffix;
        if (file_exists($suffix_path)) {
            return $suffix_path;
        }
        return $path;
    }

    const WHITELIST = [
        '0338_magic_const_types.php.expected',
    ];

    /**
     * This reads all files in a test directory (e.g. `tests/files/src`), runs
     * the analyzer on each and compares the output
     * to the files's counterpart in `tests/files/expected`
     *
     * @param string[] $test_file_list
     * @param string $expected_file_path
     * @param ?string $config_file_path
     * @return void
     * @dataProvider getTestFiles
     */
    public function testFiles($test_file_list, $expected_file_path, $config_file_path = null)
    {
        $expected_output = '';
        if (is_file($expected_file_path)) {
            // Read the expected output
            $expected_output =
                trim(file_get_contents($expected_file_path));
        }
        if (!in_array(basename($expected_file_path), self::WHITELIST)) {
            $this->assertNotRegExp('@tests[/\\\\]files[/\\\\]@', $expected_output, 'Expected output should contain a %s placeholder instead of the relative path to the file');
        }

        // Overlay any test-specific config modifiers
        if ($config_file_path) {
            foreach (require($config_file_path) as $key => $value) {
                Config::setValue($key, $value);
            }
        }

        $stream = new BufferedOutput();
        $printer = new PlainTextPrinter();
        $printer->configureOutput($stream);

        Phan::setPrinter($printer);
        Phan::setIssueCollector(new BufferingCollector());

        Phan::analyzeFileList($this->code_base, function () use ($test_file_list) : array {
            return $test_file_list;
        });

        $output = $stream->fetch();

        // Uncomment to save the output back to the expected
        // output. This should be done for error message
        // text changes and only if you promise to be careful.
        /*
        $saved_output = $output;
        $test_file_elements= explode('/', $test_file_list[0]);
        $test_file_name = array_pop($test_file_elements);
        $saved_output = preg_replace('/[^ :\n]*\/' . $test_file_name . '/', '%s', $saved_output);
        $saved_output = preg_replace('/closure_[^\(]*\(/', 'closure_%s(', $saved_output);
        if (!empty($saved_output) && strlen($saved_output) > 0) {
            $saved_output .= "\n";
        }
        file_put_contents($expected_file_path, $saved_output);
        $expected_output =
            trim(file_get_contents($expected_file_path));
        */

        $output    = preg_replace('/\r\n/', "\n", $output);

        $wanted_re = preg_replace('/\r\n/', "\n", $expected_output);
        // do preg_quote, but miss out any %r delimited sections
        $temp = "";
        $r = "%r";
        $start_offset = 0;
        $length = strlen($wanted_re);
        while ($start_offset < $length) {
            $start = strpos($wanted_re, $r, $start_offset);
            if ($start !== false) {
                // we have found a start tag
                $end = strpos($wanted_re, $r, $start + 2);
                if ($end === false) {
                    // unbalanced tag, ignore it.
                    $end = $start = $length;
                }
            } else {
                // no more %r sections
                $start = $end = $length;
            }
            // quote a non re portion of the string
            $temp = $temp . preg_quote(substr($wanted_re, $start_offset, ($start - $start_offset)), '/');
            // add the re unquoted.
            if ($end > $start) {
                $temp = $temp . '(' . substr($wanted_re, $start + 2, ($end - $start - 2)) . ')';
            }
            $start_offset = $end + 2;
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

        $this->assertRegExp(
            "/^$wanted_re\$/",
            $output,
            "Unexpected output in {$test_file_list[0]}"
        );
    }
}
