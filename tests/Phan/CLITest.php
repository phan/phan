<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\CLI;
use Phan\Config;
use Phan\Daemon\ExitException;
use Phan\Output\Printer\CSVPrinter;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Output\Printer\PylintPrinter;
use Phan\Phan;

/**
 * Unit tests of helper methods in the class CLI.
 *
 * TODO: Add more tests
 * @phan-file-suppress PhanAccessMethodInternal
 */
final class CLITest extends BaseTest
{
    /**
     * @suppress PhanAccessMethodInternal
     */
    public function setUp()
    {
        parent::setUp();
        Config::reset();
    }

    /**
     * @suppress PhanAccessMethodInternal
     */
    public function tearDown()
    {
        parent::tearDown();
        Config::reset();
    }

    /**
     * @dataProvider getFlagSuggestionStringProvider
     */
    public function testGetFlagSuggestionString(string $flag, string $expected_message)
    {
        $this->assertSame($expected_message, CLI::getFlagSuggestionString($flag));
    }

    /**
     * @return array<int,array{0:string,1:string}>
     */
    public function getFlagSuggestionStringProvider() : array
    {
        $wrap_suggestion = static function (string $text) : string {
            return " (did you mean $text?)";
        };
        return [
            ['H', $wrap_suggestion('-h')],
            ['he', $wrap_suggestion('-e or -h')],
            ['vv', $wrap_suggestion('-v')],
            ['HELP', $wrap_suggestion('--help')],
            ['ALLOW-POLYFILL-PARSER', $wrap_suggestion('--allow-polyfill-parser')],
            ['allce-polyfill-parser', $wrap_suggestion('--allow-polyfill-parser or --force-polyfill-parser')],
            ['daemonizetcphost', $wrap_suggestion('--daemonize-tcp-host')],
            ['daemonizetcpport', $wrap_suggestion('--daemonize-tcp-port')],
            ['strict-prop-checking', $wrap_suggestion('--strict-param-checking or --strict-property-checking')],
            ['process', $wrap_suggestion('--processes')],
            ['', ''],
            ['invalid-flag', ''],
        ];
    }

    /**
     * @param array<string,mixed> $expected_changed_options
     * @param array<string,mixed> $opts
     * @param array<string,mixed> $extra
     * @throws ExitException
     * @dataProvider setsConfigOptionsProvider
     */
    public function testSetsConfigOptions(array $expected_changed_options, array $opts, array $extra = [])
    {
        $opts = $opts + ['project-root-directory' => dirname(__DIR__) . '/misc/config/'];
        $expected_changed_options = $expected_changed_options + ['directory_list' => ['src']];
        if (!extension_loaded('pcntl')) {
            $expected_changed_options = $expected_changed_options + ['language_server_use_pcntl_fallback' => true];
        }
        $cli = CLI::fromRawValues($opts, []);
        $changed = [];
        foreach (Config::DEFAULT_CONFIGURATION as $key => $value) {
            $new_value = Config::getValue($key);
            if ($new_value !== $value) {
                $changed[$key] = $new_value;
            }
        }
        ksort($changed);
        ksort($expected_changed_options);
        $this->assertSame($expected_changed_options, $changed);

        $this->assertSame(['src' . DIRECTORY_SEPARATOR . 'empty.php'], $cli->getFileList());

        $printer_class = $extra['printer_class'] ?? null;
        unset($extra['printer_class']);
        if ($printer_class) {
            $this->assertInstanceOf($printer_class, Phan::$printer);
        }
        $this->assertSame($extra, []);
    }

    /**
     * @return array<int,array{0:array,1:array,2?:array}>
     */
    public function setsConfigOptionsProvider() : array
    {
        return [
            [
                [],
                [],
                ['printer_class' => PlainTextPrinter::class],
            ],
            [
                [
                    'strict_method_checking' => true,
                    'strict_param_checking' => true,
                    'strict_property_checking' => true,
                    'strict_return_checking' => true,
                ],
                ['S' => false],
            ],
            [
                [
                    'exclude_analysis_directory_list' => ['src/b.php','src/a.php'],
                ],
                ['3' => 'src/b.php,src/a.php'],
            ],
            [
                ['include_analysis_file_list' => ['src/a.php', 'src/b.php']],
                ['I' => ['src/a.php', 'src/a.php', 'src/b.php']],
            ],
            [
                ['processes' => 5, 'quick_mode' => true],
                ['processes' => '5', 'quick' => false],
            ],
            [
                [],
                ['output-mode' => 'pylint'],
                ['printer_class' => PylintPrinter::class],
            ],
            [
                [],
                ['output-mode' => 'csv'],
                ['printer_class' => CsvPrinter::class],
            ],
            [
                [
                    'color_issue_messages' => true,
                    'target_php_version' => '7.1',
                ],
                [
                    'color' => false,
                    'target-php-version' => '7.1',
                ],
            ],
            [
                [
                    'language_server_config' => [
                        'stdin' => true
                    ],
                    'language_server_enable_completion' => true,
                    'language_server_enable_go_to_definition' => true,
                    'language_server_enable_hover' => true,
                    'language_server_hide_category_of_issues' => true,
                    'plugins' => ['InvokePHPNativeSyntaxCheckPlugin'],
                    'quick_mode' => true,
                    'use_fallback_parser' => true,
                ],
                [
                    'require-config-exists' => false,
                    'language-server-on-stdin' => false,
                    'quick' => false,
                    'language-server-allow-missing-pcntl' => false,
                    'use-fallback-parser' => false,
                    'allow-polyfill-parser' => false,
                    'language-server-enable-go-to-definition' => false,
                    'language-server-enable-hover' => false,
                    'language-server-enable-completion' => false,
                    'language-server-hide-category' => false,
                    'plugin' => 'InvokePHPNativeSyntaxCheckPlugin',
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $opts
     * @dataProvider versionOptProvider
     */
    public function testVersionOpt(array $opts)
    {
        ob_start();
        try {
            CLI::fromRawValues($opts, []);
            $this->fail('should throw');
        } catch (ExitException $e) {
            $this->assertSame(0, $e->getCode());
            $this->assertSame('', $e->getMessage());
        } finally {
            $stdout = ob_get_clean();
        }
        $this->assertSame(sprintf("Phan %s\n", CLI::PHAN_VERSION), $stdout);
    }

    /** @return array<int,array> */
    public function versionOptProvider() : array
    {
        return [
            [['version' => false]],
            [['v' => false]],
        ];
    }
}
