<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Language\Type;
use Phan\Library\StringUtil;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Symfony\Component\Console\Output\BufferedOutput;

use function in_array;
use function strlen;

/**
 * Base class for tests that contain
 *
 * - a src/ folder with analyzed PHP files, and
 * - the expected/ folder of expected error (template) lines for the corresponding files.
 */
abstract class AbstractPhanFileTest extends CodeBaseAwareTest
{
    public const EXPECTED_SUFFIX = '.expected';

    /**
     * @return array<mixed,array{0:list<string>,1:string}> Array of <filename => [filename]>
     */
    abstract public function getTestFiles(): array;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Reset the config file
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        // Clear the plugins
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset the config file
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        // Clear the plugins
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * Setup our state before running each test
     */
    public function setUp(): void
    {
        parent::setUp();

        Type::clearAllMemoizations();
    }

    /**
     * Reset any changes we made to our global state
     */
    public function tearDown(): void
    {
        parent::tearDown();

        Type::clearAllMemoizations();
    }

    /**
     * Placeholder for getTestFiles dataProvider
     *
     * @param string $source_dir
     * @return array<string,array{0:array,1:string}>
     */
    final protected function scanSourceFilesDir(string $source_dir, string $expected_dir): array
    {
        $files = \array_filter(
            \scandir($source_dir) ?: [],
            static function (string $filename): bool {
                // Ignore directories and hidden files.
                return !in_array($filename, ['.', '..'], true) && \substr($filename, 0, 1) !== '.' && \preg_match('@\.php$@D', $filename);
            }
        );

        // NOTE: This is done to avoid ParseError in php-ast
        $suffixes = [];
        if (\PHP_VERSION_ID < 70200) {
            $suffixes[] = '71';
        } elseif (\PHP_VERSION_ID >= 80000) {
            if (\PHP_VERSION_ID >= 80300) {
                $suffixes[] = '83';
            }
            if (\PHP_VERSION_ID >= 80200) {
                $suffixes[] = '82';
            }
            if (\PHP_VERSION_ID >= 80100) {
                $suffixes[] = '81';
            }
            $suffixes[] = '80';
        } elseif (\PHP_VERSION_ID >= 70400) {
            $suffixes[] = '74';
        } else {
            $suffixes[] = '72';
        }

        return \array_combine(
            $files,
            \array_map(
                /** @return array{0:array{0:string},1:string} */
                static function (string $filename) use ($source_dir, $expected_dir, $suffixes): array {
                    return [
                        [self::getFileForPHPVersion($source_dir . \DIRECTORY_SEPARATOR . $filename, ...$suffixes)],
                        self::getFileForPHPVersion($expected_dir . \DIRECTORY_SEPARATOR . $filename . self::EXPECTED_SUFFIX, ...$suffixes),
                    ];
                },
                $files
            )
        );
    }

    protected static function getFileForPHPVersion(string $path, string...$suffixes): string
    {
        foreach ($suffixes as $suffix) {
            $suffix_path = $path . $suffix;
            if (\file_exists($suffix_path)) {
                return $suffix_path;
            }
        }
        return $path;
    }

    private const WHITELIST = [
        '0338_magic_const_types.php.expected',
    ];

    /**
     * This reads all files in a test directory (e.g. `tests/files/src`), runs
     * the analyzer on each and compares the output
     * to the files' counterpart in `tests/files/expected`
     *
     * @param string[] $test_file_list
     * @param string $expected_file_path
     * @param ?string $config_file_path
     * @suppress PhanThrowTypeAbsentForCall
     * @dataProvider getTestFiles
     */
    public function testFiles(array $test_file_list, string $expected_file_path, ?string $config_file_path = null): void
    {
        $expected_output = '';
        if (\is_file($expected_file_path)) {
            // Read the expected output
            // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
            $expected_output = \trim(\file_get_contents($expected_file_path));
        }
        if (!in_array(\basename($expected_file_path), self::WHITELIST, true)) {
            $this->assertNotRegExp('@tests[/\\\\]files[/\\\\]@', $expected_output, 'Expected output should contain a %s placeholder instead of the relative path to the file');
        }

        // Overlay any test-specific config modifiers
        if (StringUtil::isNonZeroLengthString($config_file_path)) {
            foreach (require($config_file_path) as $key => $value) {
                Config::setValue($key, $value);
            }
            // @phan-suppress-next-line PhanAccessMethodInternal
            ConfigPluginSet::reset();
        }

        $stream = new BufferedOutput();
        $printer = new PlainTextPrinter();
        $printer->configureOutput($stream);

        Phan::setPrinter($printer);
        Phan::setIssueCollector(new BufferingCollector());


        Phan::analyzeFileList($this->code_base, /** @return list<string> */ static function () use ($test_file_list): array {
            return $test_file_list;
        });

        $output = $stream->fetch();

        $output    = \preg_replace('/\r\n/', "\n", $output);

        $wanted_re = \preg_replace('/\r\n/', "\n", $expected_output);
        // do preg_quote, but miss out any %r delimited sections
        $temp = "";
        $r = "%r";
        $start_offset = 0;
        $length = strlen($wanted_re);
        while ($start_offset < $length) {
            $start = \strpos($wanted_re, $r, $start_offset);
            if ($start !== false) {
                // we have found a start tag
                $end = \strpos($wanted_re, $r, $start + 2);
                if ($end === false) {
                    // unbalanced tag, ignore it.
                    $end = $start = $length;
                }
            } else {
                // no more %r sections
                $start = $end = $length;
            }
            // quote a non re portion of the string
            // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
            $temp .= \preg_quote(\substr($wanted_re, $start_offset, ($start - $start_offset)), '/');
            // add the re unquoted.
            if ($end > $start) {
                $temp .= '(' . \substr($wanted_re, $start + 2, ($end - $start - 2)) . ')';
            }
            $start_offset = $end + 2;
        }
        $wanted_re = $temp;
        $wanted_re = \str_replace(['%binary_string_optional%'], 'string', $wanted_re);
        $wanted_re = \str_replace(['%unicode_string_optional%'], 'string', $wanted_re);
        $wanted_re = \str_replace(['%unicode\|string%', '%string\|unicode%'], 'string', $wanted_re);
        $wanted_re = \str_replace(['%u\|b%', '%b\|u%'], '', $wanted_re);
        // Stick to basics
        $wanted_re = \str_replace('%e', '\\' . \DIRECTORY_SEPARATOR, $wanted_re);
        $wanted_re = \str_replace('%s', '[^\r\n]+', $wanted_re);
        $wanted_re = \str_replace('%S', '[^\r\n]*', $wanted_re);
        $wanted_re = \str_replace('%a', '.+', $wanted_re);
        $wanted_re = \str_replace('%A', '.*', $wanted_re);
        $wanted_re = \str_replace('%w', '\s*', $wanted_re);
        $wanted_re = \str_replace('%i', '[+-]?\d+', $wanted_re);
        $wanted_re = \str_replace('%d', '\d+', $wanted_re);
        $wanted_re = \str_replace('%x', '[0-9a-fA-F]+', $wanted_re);
        $wanted_re = \str_replace('%f', '[+-]?\.?\d+\.?\d*(?:[Ee][+-]?\d+)?', $wanted_re);
        $wanted_re = \str_replace('%c', '.', $wanted_re);
        // %f allows two points "-.0.0" but that is the best *simple* expression
        $wanted_re_full = "/^$wanted_re\$/";

        if ($_ENV['PHAN_DUMP_NEW_TEST_EXPECTATION'] ?? null) {
            if (!\preg_match($wanted_re_full, $output)) {
                // This assumes linux/unix output, could be patched to support Windows if needed.
                // Then run `for file in tests/**/*.expected*.new; do mv $file ${file/\.new/}; done`
                // to copy all of the tests
                $suggested_re = \preg_replace('@\./tests/\S*\.php([78]\d*)?@', '%s', $output);
                $suggested_re = \preg_replace('/closure_[^\(]*\(/', 'closure_%s(', $suggested_re);
                \file_put_contents($expected_file_path . '.new', $suggested_re);
            }
        }
        // Uncomment to save the output back to the expected
        // output. This should be done for error message
        // text changes and only if you promise to be careful.
        /*
        if (!\preg_match($wanted_re_full, $output)) {
            $saved_output = $output;
            $test_file_elements = \explode('/', $test_file_list[0]);
            $test_file_name = \array_pop($test_file_elements);
            $saved_output = \preg_replace('/[^ :\n]*\/' . $test_file_name . '/', '%s', $saved_output);
            $saved_output = \preg_replace('/closure_[^\(]*\(/', 'closure_%s(', $saved_output);
            \file_put_contents($expected_file_path, $saved_output);
        }
         */

        $this->assertRegExp(
            $wanted_re_full,
            $output,
            "Unexpected output in {$test_file_list[0]}"
        );
        if (StringUtil::isNonZeroLengthString($config_file_path)) {
            foreach (require($config_file_path) as $key => $_) {
                Config::setValue($key, Config::DEFAULT_CONFIGURATION[$key]);
            }
            // @phan-suppress-next-line PhanAccessMethodInternal
            ConfigPluginSet::reset();
        }
    }
}
