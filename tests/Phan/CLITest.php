<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\CLI;
use Phan\Config;
use Phan\Daemon\ExitException;
use Phan\Exception\UsageException;
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
    public function setUp() : void
    {
        parent::setUp();
        Config::reset();
    }

    /**
     * @suppress PhanAccessMethodInternal
     */
    public function tearDown() : void
    {
        parent::tearDown();
        Config::reset();
    }

    /**
     * @dataProvider getFlagSuggestionStringProvider
     */
    public function testGetFlagSuggestionString(string $flag, string $expected_message) : void
    {
        $this->assertSame($expected_message, CLI::getFlagSuggestionString($flag));
    }

    /**
     * @return list<array{0:string,1:string}>
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
    public function testSetsConfigOptions(array $expected_changed_options, array $opts, array $extra = []) : void
    {
        $opts += ['project-root-directory' => \dirname(__DIR__) . '/misc/config/'];
        $expected_changed_options += [
            '__directory_regex' => '@^(\./)*(src)([/\\\\]|$)@',
            'directory_list' => ['src'],
        ];
        if (!\extension_loaded('pcntl')) {
            $expected_changed_options += ['language_server_use_pcntl_fallback' => true];
        }
        $cli = CLI::fromRawValues($opts, []);
        $changed = [];
        foreach (Config::DEFAULT_CONFIGURATION as $key => $value) {
            $new_value = Config::getValue($key);
            if ($new_value !== $value) {
                $changed[$key] = $new_value;
            }
        }
        \ksort($changed);
        \ksort($expected_changed_options);
        if (!\array_key_exists('color_issue_messages', $expected_changed_options)) {
            unset($changed['color_issue_messages']);
        }
        $this->assertSame($expected_changed_options, $changed);

        $this->assertSame([
            'src' . \DIRECTORY_SEPARATOR . 'a.php',
            'src' . \DIRECTORY_SEPARATOR . 'b.php',
            'src' . \DIRECTORY_SEPARATOR . 'empty.php',
        ], $cli->getFileList());

        $printer_class = $extra['printer_class'] ?? null;
        unset($extra['printer_class']);
        if ($printer_class) {
            $this->assertInstanceOf($printer_class, Phan::$printer);
        }
        $this->assertSame($extra, []);
    }

    /**
     * @return list<array{0:array,1:array,2?:array}>
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
                    'strict_object_checking' => true,
                    'strict_param_checking' => true,
                    'strict_property_checking' => true,
                    'strict_return_checking' => true,
                ],
                ['S' => false],
            ],
            [
                [
                    '__exclude_analysis_regex' => '@^(\./)*(src/b\.php|src/a\.php)([/\\\\]|$)@',
                    'exclude_analysis_directory_list' => ['src/b.php','src/a.php'],
                ],
                ['3' => 'src/b.php,src/a.php'],
            ],
            [
                [
                    '__exclude_analysis_regex' => '@^(\./)*(src\@old|\.\./src/other)([/\\\\]|$)@',
                    'exclude_analysis_directory_list' => ['src@old/','./../src/other'],
                ],
                ['3' => 'src@old/,./../src/other'],
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
            // --language-server-enable-feature are now no-ops for tested features.
            [
                [],
                [
                    'language-server-enable-go-to-definition' => false,
                    'language-server-enable-hover' => false,
                    'language-server-enable-completion' => false,
                ],
            ],
            [
                ['language_server_min_diagnostics_delay_ms' => 100.0],
                ['language-server-min-diagnostics-delay-ms' => '100'],
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
                    'language_server_enable_completion' => false,
                    'language_server_enable_go_to_definition' => false,
                    'language_server_enable_hover' => false,
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
                    'language-server-disable-go-to-definition' => false,
                    'language-server-disable-hover' => false,
                    'language-server-disable-completion' => false,
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
    public function testVersionOpt(array $opts) : void
    {
        \ob_start();
        try {
            CLI::fromRawValues($opts, []);
            $this->fail('should throw');
        } catch (ExitException $e) {
            $this->assertSame(0, $e->getCode());
            $this->assertSame('', $e->getMessage());
        } finally {
            $stdout = \ob_get_clean();
        }
        $this->assertSame(\sprintf("Phan %s\n", CLI::PHAN_VERSION), $stdout);
    }

    /** @return list<list> */
    public function versionOptProvider() : array
    {
        return [
            [['version' => false]],
            [['v' => false]],
        ];
    }

    public function testGetPluginSuggestionText() : void
    {
        $this->assertSame(
            ' (Did you mean DuplicateArrayKeyPlugin?)',
            CLI::getPluginSuggestionText('DuplicateArrayKeysPlugin')
        );
        $this->assertSame(
            ' (Did you mean HasPHPDocPlugin?)',
            CLI::getPluginSuggestionText('hasphpdocplugin')
        );
        $this->assertSame(
            '',
            CLI::getPluginSuggestionText('thisisnotsimilartoaplugin')
        );
    }

    public function testSameVersionAsNEWS() : void
    {
        $news = \file_get_contents(\dirname(__DIR__, 2) . '/NEWS.md');
        $this->assertTrue(\is_string($news));
        $versions = [];
        $lines = \explode("\n", $news);
        foreach ($lines as $i => $line) {
            if (\preg_match('@^-----@', $line)) {
                $version_line = $lines[$i - 1];
                if (\preg_match('@\b(\d+\.\d+\.\d+(-\w+)?)(.*\(dev\))?@', $version_line, $matches)) {
                    $version = $matches[1] . (!empty($matches[3]) ? '-dev' : '');
                    $versions[] = $version;
                } else {
                    $this->fail("Could not parse version line $version_line");
                }
            }
        }
        $first_version = $versions[0];
        $this->assertSame(CLI::PHAN_VERSION, $first_version, 'expected NEWS.md and CLI::PHAN_VERSION to have the same version');
        foreach ($versions as $i => $version) {
            if ($i === 0) {
                continue;
            }
            $this->assertLessThan(0, \version_compare($version, \str_replace('-dev', '', $versions[$i - 1])), "unexpected order of $version and {$versions[$i - 1]}");
        }
    }

    // Should pass both on Windows and Unix
    public function testUniqueFileList() : void
    {
        $this->assertSame([], CLI::uniqueFileList([]));
        $this->assertSame(['src/a.php', 'src/b.php'], CLI::uniqueFileList(['src/a.php', 'src' . \DIRECTORY_SEPARATOR . 'a.php', 'src/b.php', 'src//b.php']));
    }

    public function testInternalDocsUpdated() : void
    {
        global $argv;
        $old_argv = $argv;
        $argv = ['./phan'];
        try {
            \ob_start();
            CLI::usage('', null, UsageException::PRINT_EXTENDED);
            $usage_message = \ob_get_clean();
            $expected_file_contents = "```\n$usage_message```\n";
            $actual_cli_help = \file_get_contents(\dirname(__DIR__, 2) . '/internal/CLI-HELP.md');
            $this->assertSame($expected_file_contents, $actual_cli_help);
        } finally {
            $argv = $old_argv;
        }
    }
}
