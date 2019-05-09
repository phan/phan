<?php declare(strict_types=1);

namespace Phan;

use AssertionError;
use InvalidArgumentException;
use Phan\Config\Initializer;
use Phan\Daemon\ExitException;
use Phan\Exception\UsageException;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\FQSEN;
use Phan\Library\StringUtil;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Filter\CategoryIssueFilter;
use Phan\Output\Filter\ChainedIssueFilter;
use Phan\Output\Filter\FileIssueFilter;
use Phan\Output\Filter\MinimumSeverityFilter;
use Phan\Output\PrinterFactory;
use Phan\Plugin\ConfigPluginSet;
use Phan\Plugin\Internal\MethodSearcherPlugin;
use ReflectionExtension;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Terminal;
use function array_slice;
use function count;
use function in_array;
use function is_array;
use function is_resource;
use function is_string;
use function str_repeat;
use function strlen;
use const DIRECTORY_SEPARATOR;
use const EXIT_FAILURE;
use const EXIT_SUCCESS;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_IP;
use const PHP_OS;
use const STDERR;
use const STR_PAD_LEFT;

/**
 * Contains methods for parsing CLI arguments to Phan,
 * outputting to the CLI, as well as helper methods to retrieve files/folders
 * for the analyzed project.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class CLI
{
    /**
     * This should be updated to x.y.z-dev after every release, and x.y.z before a release.
     */
    const PHAN_VERSION = '1.3.4';

    /**
     * List of short flags passed to getopt
     * still available: g,n,t,u,w
     * @internal
     */
    const GETOPT_SHORT_OPTIONS = 'f:m:o:c:k:aeqbr:pid:3:y:l:xj:zhvs:SCP:I:D';

    /**
     * List of long flags passed to getopt
     * @internal
     */
    const GETOPT_LONG_OPTIONS = [
        'allow-polyfill-parser',
        'automatic-fix',
        'backward-compatibility-checks',
        'color',
        'config-file:',
        'constant-variable-detection',
        'daemonize-socket:',
        'daemonize-tcp-host:',
        'daemonize-tcp-port:',
        'dead-code-detection',
        'debug',
        'directory:',
        'disable-cache',
        'disable-plugins',
        'dump-ast',
        'dump-parsed-file-list',
        'dump-signatures-file:',
        'find-signature:',
        'exclude-directory-list:',
        'exclude-file:',
        'extended-help',
        'file-list:',
        'file-list-only:',
        'force-polyfill-parser',
        'help',
        'help-annotations',
        'ignore-undeclared',
        'include-analysis-file-list:',
        'init',
        'init-level:',
        'init-analyze-dir:',
        'init-analyze-file:',
        'init-no-composer',
        'init-overwrite',
        'language-server-analyze-only-on-save',
        'language-server-on-stdin',
        'language-server-tcp-connect:',
        'language-server-tcp-server:',
        'language-server-verbose',
        'language-server-disable-output-filter',
        'language-server-hide-category',
        'language-server-allow-missing-pcntl',
        'language-server-force-missing-pcntl',
        'language-server-require-pcntl',
        'language-server-disable-go-to-definition',
        'language-server-disable-hover',
        'language-server-disable-completion',
        'language-server-enable',
        'language-server-enable-go-to-definition',
        'language-server-enable-hover',
        'language-server-enable-completion',
        'language-server-completion-vscode',
        'markdown-issue-messages',
        'memory-limit:',
        'minimum-severity:',
        'output:',
        'output-mode:',
        'parent-constructor-required:',
        'polyfill-parse-all-element-doc-comments',
        'plugin:',
        'print-memory-usage-summary',
        'processes:',
        'progress-bar',
        'project-root-directory:',
        'quick',
        'require-config-exists',
        'signature-compatibility',
        'strict-method-checking',
        'strict-param-checking',
        'strict-property-checking',
        'strict-return-checking',
        'strict-type-checking',
        'target-php-version:',
        'unused-variable-detection',
        'use-fallback-parser',
        'version',
    ];

    /**
     * @var OutputInterface used for outputting the formatted issue messages.
     */
    private $output;

    /**
     * @return OutputInterface
     * @suppress PhanUnreferencedPublicMethod not used yet.
     */
    public function getOutput() : OutputInterface
    {
        return $this->output;
    }

    /**
     * @var array<int,string>
     * The set of file names to analyze
     */
    private $file_list_in_config = [];

    /**
     * @var array<int,string>
     * The set of file names to analyze
     */
    private $file_list = [];

    /**
     * @var bool
     * Set to true to ignore all files and directories
     * added by means other than -file-list-only on the CLI
     */
    private $file_list_only = false;

    /**
     * @var string|null
     * A possibly null path to the config file to load
     */
    private $config_file = null;

    /**
     * @param string|string[] $value
     * @return array<int,string>
     */
    public static function readCommaSeparatedListOrLists($value) : array
    {
        if (is_array($value)) {
            $value = \implode(',', $value);
        }
        $value_set = [];
        foreach (\explode(',', (string)$value) as $file) {
            if ($file === '') {
                continue;
            }
            $value_set[$file] = true;
        }
        return \array_map('strval', \array_keys($value_set));
    }

    /**
     * @param array<string,mixed> $opts
     * @param array<int,string> $argv
     * @throws UsageException
     */
    private static function checkAllArgsUsed(array $opts, array &$argv)
    {
        $pruneargv = [];
        foreach ($opts as $opt => $value) {
            foreach ($argv as $key => $chunk) {
                $regex = '/^' . (isset($opt[1]) ? '--' : '-') . \preg_quote((string) $opt, '/') . '/';

                if (in_array($chunk, is_array($value) ? $value : [$value])
                    && $argv[$key - 1][0] == '-'
                    || \preg_match($regex, $chunk)
                ) {
                    $pruneargv[] = $key;
                }
            }
        }

        while (count($pruneargv) > 0) {
            $key = \array_pop($pruneargv);
            unset($argv[$key]);
        }

        foreach ($argv as $arg) {
            if ($arg[0] == '-') {
                throw new UsageException("Unknown option '{$arg}'", EXIT_FAILURE);
            }
        }
    }

    /**
     * Creates a CLI object from argv
     */
    public static function fromArgv() : CLI
    {
        global $argv;

        // Parse command line args
        $opts = \getopt(self::GETOPT_SHORT_OPTIONS, self::GETOPT_LONG_OPTIONS);
        $opts = $opts ?? [];

        try {
            return new self($opts, $argv);
        } catch (UsageException $e) {
            self::usage($e->getMessage(), (int)$e->getCode(), $e->print_extended_help);
            exit((int)$e->getCode());  // unreachable
        } catch (ExitException $e) {
            $message = $e->getMessage();
            if ($message) {
                \fwrite(STDERR, $message);
            }
            exit($e->getCode());
        }
    }

    /**
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     *
     * @param array<string,string|array<int,mixed>|false> $opts
     * @param array<int,string> $argv
     * @return CLI
     * @throws ExitException
     * @throws UsageException
     * @internal - used for unit tests only
     */
    public static function fromRawValues(array $opts, array $argv) : CLI
    {
        return new self($opts, $argv);
    }

    /**
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     *
     * @param array<string,string|array<int,mixed>|false> $opts
     * @param array<int,string> $argv
     * @return void
     * @throws ExitException
     * @throws UsageException
     */
    private function __construct(array $opts, array $argv)
    {
        if (\array_key_exists('extended-help', $opts)) {
            throw new UsageException('', EXIT_SUCCESS, true);  // --help prints help and calls exit(0)
        }
        if (\array_key_exists('h', $opts) || \array_key_exists('help', $opts)) {
            throw new UsageException();  // --help prints help and calls exit(0)
        }
        if (\array_key_exists('help-annotations', $opts)) {
            $result = "See https://github.com/phan/phan/wiki/Annotating-Your-Source-Code for more details." . \PHP_EOL . \PHP_EOL;

            $result .= "Annotations specific to Phan:" . \PHP_EOL;
            // @phan-suppress-next-line PhanAccessClassConstantInternal
            foreach (Builder::SUPPORTED_ANNOTATIONS as $key => $_) {
                $result .= "- " . $key . \PHP_EOL;
            }
            throw new ExitException($result, EXIT_SUCCESS);
        }
        if (\array_key_exists('v', $opts ?? []) || \array_key_exists('version', $opts ?? [])) {
            \printf("Phan %s\n", self::PHAN_VERSION);
            throw new ExitException('', EXIT_SUCCESS);
        }

        // Determine the root directory of the project from which
        // we route all relative paths passed in as args
        $overridden_project_root_directory = $opts['d'] ?? $opts['project-root-directory'] ?? null;
        if (\is_string($overridden_project_root_directory)) {
            if (!\is_dir($overridden_project_root_directory)) {
                throw new UsageException(StringUtil::jsonEncode($overridden_project_root_directory) . ' is not a directory', EXIT_FAILURE);
            }
            // Set the current working directory so that relative paths within the project will work.
            // TODO: Add an option to allow searching ancestor directories?
            \chdir($overridden_project_root_directory);
        }
        $cwd = \getcwd();
        if (!is_string($cwd)) {
            \fwrite(STDERR, "Failed to find current working directory\n");
            exit(1);
        }
        Config::setProjectRootDirectory($cwd);

        if (\array_key_exists('init', $opts)) {
            $exit_code = Initializer::initPhanConfig($opts);
            if ($exit_code === 0) {
                exit($exit_code);
            }
            throw new UsageException('', $exit_code);
        }

        // Before reading the config, check for an override on
        // the location of the config file path.
        $config_file_override = $opts['k'] ?? $opts['config-file'] ?? null;
        if ($config_file_override !== null) {
            if (!is_string($config_file_override)) {
                // Doesn't work for a mix of -k and --config-file, but low priority
                throw new ExitException("Expected exactly one file for --config-file, but saw " . StringUtil::jsonEncode($config_file_override) . "\n", 1);
            }
            if (!\is_file($config_file_override)) {
                throw new ExitException("Could not find the config file override " . StringUtil::jsonEncode($config_file_override) . "\n", 1);
            }
            $this->config_file = $config_file_override;
        }

        if (isset($opts['language-server-force-missing-pcntl'])) {
            Config::setValue('language_server_use_pcntl_fallback', true);
        } elseif (!isset($opts['language-server-require-pcntl'])) {
            // --language-server-allow-missing-pcntl is now the default
            if (!\extension_loaded('pcntl')) {
                Config::setValue('language_server_use_pcntl_fallback', true);
            }
        }

        // Now that we have a root directory, attempt to read a
        // configuration file `.phan/config.php` if it exists
        $this->maybeReadConfigFile(\array_key_exists('require-config-exists', $opts));

        $this->output = new ConsoleOutput();
        $factory = new PrinterFactory();
        $printer_type = 'text';
        $minimum_severity = Config::getValue('minimum_severity');
        $mask = -1;

        foreach ($opts as $key => $value) {
            $key = (string)$key;
            switch ($key) {
                case 'r':
                case 'file-list-only':
                    // Mark it so that we don't load files through
                    // other mechanisms.
                    $this->file_list_only = true;

                    // Empty out the file list
                    $this->file_list_in_config = [];

                    // Intentionally fall through to load the
                    // file list
                case 'f':
                case 'file-list':
                    $file_list = \is_array($value) ? $value : [$value];
                    foreach ($file_list as $file_name) {
                        if (!is_string($file_name)) {
                            \error_log("invalid argument for --file-list");
                            continue;
                        }
                        $file_path = Config::projectPath($file_name);
                        if (\is_file($file_path) && \is_readable($file_path)) {
                            $lines = \file(Config::projectPath($file_name), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            if (is_array($lines)) {
                                $this->file_list_in_config = \array_merge(
                                    $this->file_list_in_config,
                                    $lines
                                );
                                continue;
                            }
                        }
                        \error_log("Unable to read file $file_path");
                    }
                    break;
                case 'l':
                case 'directory':
                    if (!$this->file_list_only) {
                        $directory_list = \is_array($value) ? $value : [$value];
                        foreach ($directory_list as $directory_name) {
                            if (!is_string($directory_name)) {
                                \error_log("Invalid --directory setting");
                                return;
                            }
                            $this->file_list_in_config = \array_merge(
                                $this->file_list_in_config,
                                \array_values($this->directoryNameToFileList(
                                    $directory_name
                                ))
                            );
                        }
                    }
                    break;
                case 'k':
                case 'config-file':
                    break;
                case 'm':
                case 'output-mode':
                    if (!is_string($value) || !in_array($value, $factory->getTypes(), true)) {
                        throw new UsageException(
                            \sprintf(
                                'Unknown output mode %s. Known values are [%s]',
                                StringUtil::jsonEncode($value),
                                \implode(',', $factory->getTypes())
                            ),
                            EXIT_FAILURE
                        );
                    }
                    $printer_type = $value;
                    break;
                case 'c':
                case 'parent-constructor-required':
                    Config::setValue('parent_constructor_required', \explode(',', $value));
                    break;
                case 'q':
                case 'quick':
                    Config::setValue('quick_mode', true);
                    break;
                case 'b':
                case 'backward-compatibility-checks':
                    Config::setValue('backward_compatibility_checks', true);
                    break;
                case 'p':
                case 'progress-bar':
                    Config::setValue('progress_bar', true);
                    break;
                case 'D':
                case 'debug':
                    Config::setValue('debug_output', true);
                    break;
                case 'a':
                case 'dump-ast':
                    Config::setValue('dump_ast', true);
                    break;
                case 'dump-parsed-file-list':
                    Config::setValue('dump_parsed_file_list', true);
                    break;
                case 'dump-signatures-file':
                    Config::setValue('dump_signatures_file', $value);
                    break;
                case 'find-signature':
                    try {
                        if (!is_string($value)) {
                            throw new InvalidArgumentException("Expected a string, got " . \json_encode($value));
                        }
                        // @phan-suppress-next-line PhanAccessMethodInternal
                        MethodSearcherPlugin::setSearchString($value);
                    } catch (InvalidArgumentException $e) {
                        throw new UsageException("Invalid argument '$value' to --find-signature. Error: " . $e->getMessage() . "\n", EXIT_FAILURE);
                    }

                    Config::setValue('plugins', \array_merge(
                        Config::getValue('plugins'),
                        [__DIR__ . '/Plugin/Internal/MethodSearcherPluginLoader.php']
                    ));
                    break;
                case 'automatic-fix':
                    Config::setValue('plugins', \array_merge(
                        Config::getValue('plugins'),
                        [__DIR__ . '/Plugin/Internal/IssueFixingPlugin.php']
                    ));
                    break;
                case 'o':
                case 'output':
                    if (!is_string($value)) {
                        throw new UsageException(\sprintf("Invalid arguments to --output: args=%s\n", StringUtil::jsonEncode($value)), EXIT_FAILURE);
                    }
                    $output_file = \fopen($value, 'w');
                    if (!is_resource($output_file)) {
                        throw new UsageException("Failed to open output file '$value'\n", EXIT_FAILURE);
                    }
                    $this->output = new StreamOutput($output_file);
                    break;
                case 'i':
                case 'ignore-undeclared':
                    $mask &= ~Issue::CATEGORY_UNDEFINED;
                    break;
                case '3':
                case 'exclude-directory-list':
                    // @phan-suppress-next-line PhanPossiblyFalseTypeArgument
                    Config::setValue('exclude_analysis_directory_list', self::readCommaSeparatedListOrLists($value));
                    break;
                case 'exclude-file':
                    Config::setValue('exclude_file_list', \array_merge(
                        Config::getValue('exclude_file_list'),
                        \is_array($value) ? $value : [$value]
                    ));
                    break;
                case 'I':
                case 'include-analysis-file-list':
                    // @phan-suppress-next-line PhanPossiblyFalseTypeArgument
                    Config::setValue('include_analysis_file_list', self::readCommaSeparatedListOrLists($value));
                    break;
                case 'j':
                case 'processes':
                    Config::setValue('processes', (int)$value);
                    break;
                case 'z':
                case 'signature-compatibility':
                    Config::setValue('analyze_signature_compatibility', (bool)$value);
                    break;
                case 'y':
                case 'minimum-severity':
                    $minimum_severity = (int)$value;
                    break;
                case 'target-php-version':
                    Config::setValue('target_php_version', $value);
                    break;
                case 'polyfill-parse-all-element-doc-comments':
                    Config::setValue('polyfill_parse_all_element_doc_comments', true);
                    break;
                case 'd':
                case 'project-root-directory':
                    // We handle this flag before parsing options so
                    // that we can get the project root directory to
                    // base other config flags values on
                    break;
                case 'require-config-exists':
                    break;  // handled earlier.
                case 'language-server-allow-missing-pcntl':
                case 'language-server-force-missing-pcntl':
                case 'language-server-require-pcntl':
                    break;  // handled earlier
                case 'language-server-hide-category':
                    Config::setValue('language_server_hide_category_of_issues', true);
                    break;
                case 'disable-cache':
                    Config::setValue('cache_polyfill_asts', false);
                    break;
                case 'disable-plugins':
                    // Slightly faster, e.g. for daemon mode with lowest latency (along with --quick).
                    Config::setValue('plugins', []);
                    break;
                case 'P':
                case 'plugin':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    Config::setValue(
                        'plugins',
                        \array_unique(\array_merge(Config::getValue('plugins'), $value))
                    );
                    break;
                case 'use-fallback-parser':
                    Config::setValue('use_fallback_parser', true);
                    break;
                case 'strict-method-checking':
                    Config::setValue('strict_method_checking', true);
                    break;
                case 'strict-param-checking':
                    Config::setValue('strict_param_checking', true);
                    break;
                case 'strict-property-checking':
                    Config::setValue('strict_property_checking', true);
                    break;
                case 'strict-return-checking':
                    Config::setValue('strict_return_checking', true);
                    break;
                case 'S':
                case 'strict-type-checking':
                    Config::setValue('strict_method_checking', true);
                    Config::setValue('strict_param_checking', true);
                    Config::setValue('strict_property_checking', true);
                    Config::setValue('strict_return_checking', true);
                    break;
                case 's':
                case 'daemonize-socket':
                    self::checkCanDaemonize('unix', $key);
                    if (!is_string($value)) {
                        throw new UsageException(\sprintf("Invalid arguments to --daemonize-socket: args=%s", StringUtil::jsonEncode($value)), EXIT_FAILURE);
                    }
                    $socket_dirname = \realpath(\dirname($value));
                    if (!is_string($socket_dirname) || !\file_exists($socket_dirname) || !\is_dir($socket_dirname)) {
                        $msg = \sprintf(
                            'Requested to create Unix socket server in %s, but folder %s does not exist',
                            StringUtil::jsonEncode($value),
                            StringUtil::jsonEncode($socket_dirname)
                        );
                        throw new UsageException($msg, 1);
                    } else {
                        Config::setValue('daemonize_socket', $value);  // Daemonize. Assumes the file list won't change. Accepts requests over a Unix socket, or some other IPC mechanism.
                    }
                    break;
                    // TODO(possible idea): HTTP server binding to 127.0.0.1, daemonize-http-port.
                case 'daemonize-tcp-host':
                    $this->checkCanDaemonize('tcp', $key);
                    Config::setValue('daemonize_tcp', true);
                    $host = \filter_var($value, FILTER_VALIDATE_IP);
                    if (\strcasecmp($value, 'default') !== 0 && !$host) {
                        throw new UsageException("daemonize-tcp-host must be the string 'default' or a valid hostname, got '$value'", 1);
                    }
                    if ($host) {
                        Config::setValue('daemonize_tcp_host', $host);
                    }
                    break;
                case 'daemonize-tcp-port':
                    $this->checkCanDaemonize('tcp', $key);
                    Config::setValue('daemonize_tcp', true);
                    $port = \filter_var($value, FILTER_VALIDATE_INT);
                    if (\strcasecmp($value, 'default') !== 0 && !($port >= 1024 && $port <= 65535)) {
                        throw new UsageException("daemonize-tcp-port must be the string 'default' or a value between 1024 and 65535, got '$value'", 1);
                    }
                    if ($port) {
                        Config::setValue('daemonize_tcp_port', $port);
                    }
                    break;
                case 'language-server-on-stdin':
                    Config::setValue('language_server_config', ['stdin' => true]);
                    break;
                case 'language-server-tcp-server':
                    // TODO: could validate?
                    Config::setValue('language_server_config', ['tcp-server' => $value]);
                    break;
                case 'language-server-tcp-connect':
                    Config::setValue('language_server_config', ['tcp' => $value]);
                    break;
                case 'language-server-analyze-only-on-save':
                    Config::setValue('language_server_analyze_only_on_save', true);
                    break;
                case 'language-server-disable-go-to-definition':
                    Config::setValue('language_server_enable_go_to_definition', false);
                    break;
                case 'language-server-enable-go-to-definition':
                    Config::setValue('language_server_enable_go_to_definition', true);
                    break;
                case 'language-server-disable-hover':
                    Config::setValue('language_server_enable_hover', false);
                    break;
                case 'language-server-enable-hover':
                    Config::setValue('language_server_enable_hover', true);
                    break;
                case 'language-server-completion-vscode':
                    break;
                case 'language-server-disable-completion':
                    Config::setValue('language_server_enable_completion', false);
                    break;
                case 'language-server-enable-completion':
                    Config::setValue(
                        'language_server_enable_completion',
                        isset($opts['language-server-completion-vscode']) ? Config::COMPLETION_VSCODE : true
                    );
                    break;
                case 'language-server-verbose':
                    Config::setValue('language_server_debug_level', 'info');
                    break;
                case 'language-server-disable-output-filter':
                    Config::setValue('language_server_disable_output_filter', true);
                    break;
                case 'x':
                case 'dead-code-detection':
                    Config::setValue('dead_code_detection', true);
                    break;
                case 'unused-variable-detection':
                    Config::setValue('unused_variable_detection', true);
                    break;
                case 'constant-variable-detection':
                    Config::setValue('constant_variable_detection', true);
                    Config::setValue('unused_variable_detection', true);
                    break;
                case 'allow-polyfill-parser':
                    // Just check if it's installed and of a new enough version.
                    // Assume that if there is an installation, it works, and warn later in ensureASTParserExists()
                    if (!\extension_loaded('ast')) {
                        Config::setValue('use_polyfill_parser', true);
                        break;
                    }
                    $ast_version = (new ReflectionExtension('ast'))->getVersion();
                    if (\version_compare($ast_version, '0.1.5') < 0) {
                        Config::setValue('use_polyfill_parser', true);
                        break;
                    }
                    break;
                case 'force-polyfill-parser':
                    Config::setValue('use_polyfill_parser', true);
                    break;
                case 'memory-limit':
                    if (\preg_match('@^([1-9][0-9]*)([KMG])?$@', $value, $match)) {
                        \ini_set('memory_limit', $value);
                    } else {
                        \fwrite(STDERR, "Invalid --memory-limit '$value', ignoring\n");
                    }
                    break;
                case 'print-memory-usage-summary':
                    Config::setValue('print_memory_usage_summary', true);
                    break;
                case 'markdown-issue-messages':
                    Config::setValue('markdown_issue_messages', true);
                    break;
                case 'C':
                case 'color':
                    Config::setValue('color_issue_messages', true);
                    break;
                default:
                    throw new UsageException("Unknown option '-$key'" . self::getFlagSuggestionString($key), EXIT_FAILURE);
            }
        }

        self::checkPluginsExist();
        self::ensureASTParserExists();

        $output = $this->output;
        $printer = $factory->getPrinter($printer_type, $output);
        $filter  = new ChainedIssueFilter([
            new FileIssueFilter(new Phan()),
            new MinimumSeverityFilter($minimum_severity),
            new CategoryIssueFilter($mask)
        ]);
        $collector = new BufferingCollector($filter);

        self::checkAllArgsUsed($opts, $argv);

        Phan::setPrinter($printer);
        Phan::setIssueCollector($collector);
        if (!$this->file_list_only) {
            // Merge in any remaining args on the CLI
            $this->file_list_in_config = \array_merge(
                $this->file_list_in_config,
                array_slice($argv, 1)
            );
        }

        $this->recomputeFileList();

        // We can't run dead code detection on multiple cores because
        // we need to update reference lists in a globally accessible
        // way during analysis. With our parallelization mechanism, there
        // is no shared state between processes, making it impossible to
        // have a complete set of reference lists.
        if (Config::getValue('processes') !== 1
            && Config::getValue('dead_code_detection')) {
            throw new AssertionError("We cannot run dead code detection on more than one core.");
        }
    }

    private static function checkPluginsExist()
    {
        $all_plugins_exist = true;
        foreach (Config::getValue('plugins') as $plugin_path_or_name) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $plugin_file_name = ConfigPluginSet::normalizePluginPath($plugin_path_or_name);
            if (!\is_file($plugin_file_name)) {
                if ($plugin_file_name === $plugin_path_or_name) {
                    $details = '';
                } else {
                    $details = ' (Referenced as ' . StringUtil::jsonEncode($plugin_path_or_name) . ')';
                    $details .= self::getPluginSuggestionText($plugin_path_or_name);
                }
                \fprintf(
                    STDERR,
                    "Phan could not find plugin %s%s\n",
                    StringUtil::jsonEncode($plugin_file_name),
                    $details
                );
                $all_plugins_exist = false;
            }
        }
        if (!$all_plugins_exist) {
            \fwrite(STDERR, "Exiting due to invalid plugin config.\n");
            exit(1);
        }
    }

    /**
     * @internal (visible for tests)
     */
    public static function getPluginSuggestionText(string $plugin_path_or_name) : string
    {
        $plugin_dirname = ConfigPluginSet::getBuiltinPluginDirectory();
        $candidates = [];
        foreach (\scandir($plugin_dirname) as $basename) {
            if (\substr($basename, -4) !== '.php') {
                continue;
            }
            $plugin_name = \substr($basename, 0, -4);
            $candidates[$plugin_name] = $plugin_name;
        }
        $suggestions = IssueFixSuggester::getSuggestionsForStringSet($plugin_path_or_name, $candidates);
        if (!$suggestions) {
            return '';
        }
        return ' (Did you mean ' . \implode(' or ', $suggestions) . '?)';
    }

    /**
     * @return void
     */
    public function recomputeFileList()
    {
        $this->file_list = $this->file_list_in_config;

        if (!$this->file_list_only) {
            // Merge in any files given in the config
            /** @var array<int,string> */
            $this->file_list = \array_merge(
                $this->file_list,
                Config::getValue('file_list')
            );

            // Merge in any directories given in the config
            foreach (Config::getValue('directory_list') as $directory_name) {
                $this->file_list = \array_merge(
                    $this->file_list,
                    \array_values(self::directoryNameToFileList($directory_name))
                );
            }

            // Don't scan anything twice
            $this->file_list = \array_unique($this->file_list);
        }

        // Exclude any files that should be excluded from
        // parsing and analysis (not read at all)
        if (count(Config::getValue('exclude_file_list')) > 0) {
            $exclude_file_set = [];
            foreach (Config::getValue('exclude_file_list') as $file) {
                $normalized_file = \str_replace('\\', '/', $file);
                $exclude_file_set[$normalized_file] = true;
                $exclude_file_set["./$normalized_file"] = true;
            }

            $this->file_list = \array_filter(
                $this->file_list,
                static function (string $file) use ($exclude_file_set) : bool {
                    // Handle edge cases such as 'mydir/subdir\subsubdir' on Windows, if mydir/subdir was in the Phan config.
                    return !isset($exclude_file_set[\str_replace('\\', '/', $file)]);
                }
            );
        }
    }

    /**
     * @return void - exits on usage error
     * @throws UsageException
     */
    private static function checkCanDaemonize(string $protocol, string $opt)
    {
        $opt = strlen($opt) >= 2 ? "--$opt" : "-$opt";
        if (!in_array($protocol, \stream_get_transports())) {
            throw new UsageException("The $protocol:///path/to/file schema is not supported on this system, cannot create a daemon with $opt", 1);
        }
        if (!Config::getValue('language_server_use_pcntl_fallback') && !\function_exists('pcntl_fork')) {
            throw new UsageException("The pcntl extension is not available to fork a new process, so $opt will not be able to create workers to respond to requests.", 1);
        }
        if ($opt === '--daemonize-socket' && Config::getValue('daemonize_tcp')) {
            throw new UsageException('Can specify --daemonize-socket or --daemonize-tcp-port only once', 1);
        } elseif (($opt === '--daemonize-tcp-host' || $opt === '--daemonize-tcp-port') && Config::getValue('daemonize_socket')) {
            throw new UsageException("Can specify --daemonize-socket or $opt only once", 1);
        }
    }

    /**
     * @return array<int,string>
     * Get the set of files to analyze
     */
    public function getFileList() : array
    {
        return $this->file_list;
    }

    const INIT_HELP = <<<'EOT'
 --init
   [--init-level=3]
   [--init-analyze-dir=path/to/src]
   [--init-analyze-file=path/to/file.php]
   [--init-no-composer]

  Generates a `.phan/config.php` in the current directory
  based on the project's composer.json.
  The logic used to generate the config file is currently very simple.
  Some third party classes (e.g. in vendor/)
  will need to be manually added to 'directory_list' or excluded,
  and you may end up with a large number of issues to be manually suppressed.
  See https://github.com/phan/phan/wiki/Tutorial-for-Analyzing-a-Large-Sloppy-Code-Base

  [--init-level] affects the generated settings in `.phan/config.php`
    (e.g. null_casts_as_array).
    `--init-level` can be set to 1 (strictest) to 5 (least strict)
  [--init-analyze-dir] can be used as a relative path alongside directories
    that Phan infers from composer.json's "autoload" settings
  [--init-analyze-file] can be used as a relative path alongside files
    that Phan infers from composer.json's "bin" settings
  [--init-no-composer] can be used to tell Phan that the project
    is not a composer project.
    Phan will not check for composer.json or vendor/,
    and will not include those paths in the generated config.
  [--init-overwrite] will allow 'phan --init' to overwrite .phan/config.php.

EOT;

    // FIXME: If I stop using defined() in UnionTypeVisitor,
    // this will warn about the undefined constant EXIT_SUCCESS when a
    // user-defined constant is used in parse phase in a function declaration
    private static function usage(string $msg = '', int $exit_code = EXIT_SUCCESS, bool $print_extended_help = false)
    {
        global $argv;

        if ($msg !== '') {
            echo "$msg\n";
        }

        $init_help = self::INIT_HELP;
        echo <<<EOB
Usage: {$argv[0]} [options] [files...]
 -f, --file-list <filename>
  A file containing a list of PHP files to be analyzed

 -l, --directory <directory>
  A directory that should be parsed for class and
  method information. After excluding the directories
  defined in --exclude-directory-list, the remaining
  files will be statically analyzed for errors.

  Thus, both first-party and third-party code being used by
  your application should be included in this list.

  You may include multiple `--directory DIR` options.

 --exclude-file <file>
  A file that should not be parsed or analyzed (or read
  at all). This is useful for excluding hopelessly
  unanalyzable files.

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories that defines files
  that will be excluded from static analysis, but whose
  class and method information should be included.
  (can be repeated, ignored if --include-analysis-directory-list is used)

  Generally, you'll want to include the directories for
  third-party code (such as "vendor/") in this list.

 -I, --include-analysis-file-list <file_list>
  A comma-separated list of files that will be included in
  static analysis. All others won't be analyzed.
  (can be repeated)

  This is primarily intended for performing standalone
  incremental analysis.

 -d, --project-root-directory </path/to/project>
  Hunt for a directory named `.phan` in the provided directory
  and read configuration file `.phan/config.php` from that path.

 -r, --file-list-only
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is unlikely to be useful.

 -k, --config-file
  A path to a config file to load (instead of the default of
  `.phan/config.php`).

 -m <mode>, --output-mode
  Output mode from 'text', 'json', 'csv', 'codeclimate', 'checkstyle', or 'pylint'

 -o, --output <filename>
  Output filename

$init_help
 -C, --color
  Add colors to the outputted issues. Tested in Unix.
  This is recommended for only the default --output-mode ('text')

 -p, --progress-bar
  Show progress bar

 -D, --debug
  Print debugging output to stderr. Useful for looking into performance issues or crashes.

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

 --target-php-version {7.0,7.1,7.2,7.3,7.4,native}
  The PHP version that the codebase will be checked for compatibility against.
  For best results, the PHP binary used to run Phan should have the same PHP version.
  (Phan relies on Reflection for some param counts
   and checks for undefined classes/methods/functions)

 -i, --ignore-undeclared
  Ignore undeclared functions and classes

 -y, --minimum-severity <level in {0,5,10}>
  Minimum severity level (low=0, normal=5, critical=10) to report.
  Defaults to 0.

 -c, --parent-constructor-required
  Comma-separated list of classes that require
  parent::__construct() to be called

 -x, --dead-code-detection
  Emit issues for classes, methods, functions, constants and
  properties that are probably never referenced and can
  be removed. This implies `--unused-variable-detection`.

 --unused-variable-detection
  Emit issues for variables, parameters and closure use variables
  that are probably never referenced.
  This has a few known false positives, e.g. for loops or branches.

 -j, --processes <int>
  The number of parallel processes to run during the analysis
  phase. Defaults to 1.

 -z, --signature-compatibility
  Analyze signatures for methods that are overrides to ensure
  compatibility with what they're overriding.

 --disable-cache
  Don't cache any ASTs from the polyfill/fallback.

  ASTs from the native parser (php-ast) don't need to be cached.

  This is useful if Phan will be run only once and php-ast is unavailable (e.g. in Travis)

 --disable-plugins
  Don't run any plugins. Slightly faster.

 -P, --plugin <pluginName|path/to/Plugin.php>
  Add a plugin to run. This flag can be repeated.
  (Either pass the name of the plugin or a relative/absolute path to the plugin)

 --strict-method-checking
  Warn if any type in a method invocation's object is definitely not an object,
  or any type in an invoked expression is not a callable.
  (Enables the config option `strict_method_checking`)

 --strict-param-checking
  Warn if any type in an argument's union type cannot be cast to
  the parameter's expected union type.
  (Enables the config option `strict_param_checking`)

 --strict-property-checking
  Warn if any type in a property assignment's union type
  cannot be cast to a type in the property's declared union type.
  (Enables the config option `strict_property_checking`)

 --strict-return-checking
  Warn if any type in a returned value's union type
  cannot be cast to the declared return type.
  (Enables the config option `strict_return_checking`)

 -S, --strict-type-checking
  Equivalent to
  `--strict-method-checking --strict-param-checking --strict-property-checking --strict-return-checking`.

 --use-fallback-parser
  If a file to be analyzed is syntactically invalid
  (i.e. "php --syntax-check path/to/file" would emit a syntax error),
  then retry, using a different, slower error tolerant parser to parse it.
  (And phan will then analyze what could be parsed).
  This flag is experimental and may result in unexpected exceptions or errors.
  This flag does not affect excluded files and directories.

 --allow-polyfill-parser
  If the `php-ast` extension isn't available or is an outdated version,
  then use a slower parser (based on tolerant-php-parser) instead.
  Note that https://github.com/Microsoft/tolerant-php-parser
  has some known bugs which may result in false positive parse errors.

 --force-polyfill-parser
  Use a slower parser (based on tolerant-php-parser) instead of the native parser,
  even if the native parser is available.
  Useful mainly for debugging.

 -s, --daemonize-socket </path/to/file.sock>
  Unix socket for Phan to listen for requests on, in daemon mode.

 --daemonize-tcp-host
  TCP hostname for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for host 127.0.0.1, or `0.0.0.0` for
  usage with Docker). `phan_client` can be used to communicate with the Phan Daemon.

 --daemonize-tcp-port <default|1024-65535>
  TCP port for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for port 4846.)
  `phan_client` can be used to communicate with the Phan Daemon.

 -v, --version
  Print Phan's version number

 -h, --help
  This help information

 --extended-help
  This help information, plus less commonly used flags
  (E.g. for daemon mode)

EOB;
        if ($print_extended_help) {
            echo <<<EOB

Extended help:
 -a, --dump-ast
  Emit an AST for each file rather than analyze.

 --dump-parsed-file-list
  Emit a newline-separated list of files Phan would parse to stdout.
  This is useful to verify that options such as exclude_file_regex are
  properly set up, or to run other checks on the files Phan would parse.

 --dump-signatures-file <filename>
  Emit JSON serialized signatures to the given file.
  This uses a method signature format similar to FunctionSignatureMap.php.

 --automatic-fix
  Automatically fix any issues Phan is capable of fixing.
  NOTE: This is a work in progress and limited to a small subset of issues
  (e.g. unused imports on their own line)

 --find-signature 'paramUnionType1->paramUnionType2->returnUnionType'
  Find a signature in the analyzed codebase that is similar to the argument.
  See tool/phoogle for examples.

 --memory-limit <memory_limit>
  Sets the memory limit for analysis (per process).
  This is useful when developing or when you want guarantees on memory limits.
  K, M, and G are optional suffixes (Kilobytes, Megabytes, Gigabytes).

 --print-memory-usage-summary
  Prints a summary of memory usage and maximum memory usage.
  This is accurate when there is one analysis process.

 --markdown-issue-messages
  Emit issue messages with markdown formatting.

 --polyfill-parse-all-element-doc-comments
  Makes the polyfill aware of doc comments on class constants and declare statements
  even when imitating parsing a PHP 7.0 codebase.

 --constant-variable-detection
  Emit issues for variables that could be replaced with literals or constants.
  (i.e. they are declared once (as a constant expression) and never modified).
  This is almost entirely false positives for most coding styles.
  Implies --unused-variable-detection

 --language-server-on-stdin
  Start the language server (For the Language Server protocol).
  This is a different protocol from --daemonize, clients for various IDEs already exist.

 --language-server-tcp-server <addr>
  Start the language server listening for TCP connections on <addr> (e.g. 127.0.0.1:<port>)

 --language-server-tcp-connect <addr>
  Start the language server and connect to the client listening on <addr> (e.g. 127.0.0.1:<port>)

 --language-server-analyze-only-on-save
  Prevent the client from sending change notifications (Only notify the language server when the user saves a document)
  This significantly reduces CPU usage, but clients won't get notifications about issues immediately.

 --language-server-disable-go-to-definition, --language-server-enable-go-to-definition
  Disables/Enables support for "Go To Definition" and "Go To Type Definition" in the Phan Language Server.
  Disabled by default.

 --language-server-disable-hover, --language-server-enable-hover
  Disables/Enables support for "Hover" in the Phan Language Server.
  Disabled by default.

 --language-server-disable-completion, --language-server-enable-completion
  Disables/Enables support for "Completion" in the Phan Language Server.
  Disabled by default.

 --language-server-completion-vscode
  Adds a workaround to make completion of variables and static properties
  that are compatible with language clients such as VS Code.

 --language-server-verbose
  Emit verbose logging messages related to the language server implementation to stderr.
  This is useful when developing or debugging language server clients.

 --language-server-disable-output-filter
  Emit all issues detected from the language server (e.g. invalid phpdoc in parsed files),
  not just issues in files currently open in the editor/IDE.
  This can be very verbose and has more false positives.

  This is useful when developing or debugging language server clients.

 --language-server-allow-missing-pcntl
  No-op (This is the default behavior).
  Allow the fallback that doesn't use pcntl (New and experimental) to be used if the pcntl extension is not installed.
  This is useful for running the language server on Windows.

 --language-server-hide-category
  Remove the Phan issue category from diagnostic messages.
  Makes issue messages slightly shorter.

 --language-server-force-missing-pcntl
  Force Phan to use the fallback for when pcntl is absent (New and experimental). Useful for debugging that fallback.

 --language-server-require-pcntl
  Don't start the language server if PCNTL isn't installed (don't use the fallback). Useful for debugging.

 --require-config-exists
  Exit immediately with an error code if `.phan/config.php` does not exist.

 --help-annotations
  Print details on annotations supported by Phan

EOB;
        }
        exit($exit_code);
    }

    /**
     * Finds potentially misspelled flags and returns them as a string
     *
     * This will use levenshtein distance, showing the first one or two flags
     * which match with a distance of <= 5
     *
     * @param string $key Misspelled key to attempt to correct
     * @return string
     * @internal
     */
    public static function getFlagSuggestionString(
        string $key
    ) : string {
        $trim = static function (string $s) : string {
            return \rtrim($s, ':');
        };
        $generate_suggestion = static function (string $suggestion) : string {
            return (strlen($suggestion) === 1 ? '-' : '--') . $suggestion;
        };
        $generate_suggestion_text = static function (string $suggestion, string ...$other_suggestions) use ($generate_suggestion) : string {
            $suggestions = \array_merge([$suggestion], $other_suggestions);
            return ' (did you mean ' . \implode(' or ', \array_map($generate_suggestion, $suggestions)) . '?)';
        };
        $short_options = \array_filter(\array_map($trim, \str_split(self::GETOPT_SHORT_OPTIONS)));
        if (strlen($key) === 1) {
            $alternate = \ctype_lower($key) ? \strtoupper($key) : \strtolower($key);
            if (in_array($alternate, $short_options)) {
                return $generate_suggestion_text($alternate);
            }
            return '';
        } elseif ($key === '') {
            return '';
        }
        // include short options in case a typo is made like -aa instead of -a
        $known_flags = \array_merge(self::GETOPT_LONG_OPTIONS, $short_options);

        $known_flags = \array_map($trim, $known_flags);

        $similarities = [];

        $key_lower = \strtolower($key);
        foreach ($known_flags as $flag) {
            if (strlen($flag) === 1 && \stripos($key, $flag) === false) {
                // Skip over suggestions of flags that have no common characters
                continue;
            }
            $distance = \levenshtein($key_lower, \strtolower($flag));
            // distance > 5 is to far off to be a typo
            // Make sure that if two flags have the same distance, ties are sorted alphabetically
            if ($distance <= 5) {
                $similarities[$flag] = [$distance, "x" . \strtolower($flag), $flag];
            }
        }

        \asort($similarities); // retain keys and sort descending
        $similarity_values = \array_values($similarities);

        if (count($similarity_values) >= 2 && ($similarity_values[1][0] <= $similarity_values[0][0] + 1)) {
            // If the next-closest suggestion isn't close to as similar as the closest suggestion, just return the closest suggestion
            return $generate_suggestion_text($similarity_values[0][2], $similarity_values[1][2]);
        } elseif (count($similarity_values) >= 1) {
            return $generate_suggestion_text($similarity_values[0][2]);
        }
        return '';
    }

    /**
     * Checks if a file (not a folder) which has potentially not yet been created on disk should be parsed.
     * @param string $file_path a relative path to a file within the project
     */
    public static function shouldParse(string $file_path) : bool
    {
        $exclude_file_regex = Config::getValue('exclude_file_regex');
        if ($exclude_file_regex && self::isPathExcludedByRegex($exclude_file_regex, $file_path)) {
            return false;
        }
        $file_extensions = Config::getValue('analyzed_file_extensions');

        if (!\is_array($file_extensions) || count($file_extensions) === 0) {
            return false;
        }
        $extension = \pathinfo($file_path, \PATHINFO_EXTENSION);
        if (!$extension || !in_array($extension, $file_extensions)) {
            return false;
        }

        $directory_regex = Config::getValue('__directory_regex');
        return $directory_regex && \preg_match($directory_regex, $file_path) > 0;
    }

    /**
     * @param string $directory_name
     * The name of a directory to scan for files ending in `.php`.
     *
     * @return array<string,string>
     * A list of PHP files in the given directory
     *
     * @throws InvalidArgumentException
     * if there is nothing to analyze
     */
    private static function directoryNameToFileList(
        string $directory_name
    ) : array {
        $file_list = [];

        try {
            $file_extensions = Config::getValue('analyzed_file_extensions');

            if (!\is_array($file_extensions) || count($file_extensions) === 0) {
                throw new InvalidArgumentException(
                    'Empty list in config analyzed_file_extensions. Nothing to analyze.'
                );
            }

            $exclude_file_regex = Config::getValue('exclude_file_regex');
            $iterator = new \CallbackFilterIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $directory_name,
                        \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                    )
                ),
                static function (\SplFileInfo $file_info) use ($file_extensions, $exclude_file_regex) : bool {
                    if (!in_array($file_info->getExtension(), $file_extensions, true)) {
                        return false;
                    }

                    if (!$file_info->isFile() || !$file_info->isReadable()) {
                        $file_path = $file_info->getRealPath();
                        \error_log("Unable to read file {$file_path}");
                        return false;
                    }

                    // Compare exclude_file_regex against the relative path within the project
                    // (E.g. src/foo.php)
                    if ($exclude_file_regex && self::isPathExcludedByRegex($exclude_file_regex, $file_info->getPathname())) {
                        return false;
                    }

                    return true;
                }
            );

            $file_list = \array_keys(\iterator_to_array($iterator));
        } catch (\Exception $exception) {
            \error_log($exception->getMessage());
        }

        // Normalize leading './' in paths.
        $normalized_file_list = [];
        foreach ($file_list as $file_path) {
            $file_path = \preg_replace('@^(\.[/\\\\]+)+@', '', $file_path);
            $normalized_file_list[$file_path] = $file_path;
        }
        \usort($normalized_file_list, static function (string $a, string $b) : int {
            // Sort lexicographically by paths **within the results for a directory**,
            // to work around some file systems not returning results lexicographically.
            // Keep directories together by replacing directory separators with the null byte
            // (E.g. "a.b" is lexicographically less than "a/b", but "aab" is greater than "a/b")
            return \strcmp(\preg_replace("@[/\\\\]+@", "\0", $a), \preg_replace("@[/\\\\]+@", "\0", $b));
        });

        return $normalized_file_list;
    }

    /**
     * Returns true if the progress bar was requested and it makes sense to display.
     */
    public static function shouldShowProgress() : bool
    {
        return (Config::getValue('progress_bar') || Config::getValue('debug_output')) &&
            !Config::getValue('dump_ast') &&
            !self::isDaemonOrLanguageServer();
    }

    /**
     * Returns true if this is a daemon or language server responding to requests
     */
    public static function isDaemonOrLanguageServer() : bool
    {
        return Config::getValue('daemonize_tcp') ||
            Config::getValue('daemonize_socket') ||
            Config::getValue('language_server_config');
    }

    /**
     * Should this show --debug output
     */
    public static function shouldShowDebugOutput() : bool
    {
        return Config::getValue('debug_output') && !self::isDaemonOrLanguageServer();
    }

    /**
     * Check if a path name is excluded by regex, in a platform independent way.
     * Normalizes $path_name on Windows so that '/' is always the directory separator.
     *
     * @param string $exclude_file_regex - PHP regex
     * @param string $path_name - path name within project, beginning with user-provided directory name.
     *                            On windows, may contain '\'.
     *
     * @return bool - True if the user's configured regex is meant to exclude $path_name
     */
    private static function isPathExcludedByRegex(
        string $exclude_file_regex,
        string $path_name
    ) : bool {
        // Make this behave the same way on Linux/Unix and on Windows.
        if (DIRECTORY_SEPARATOR === '\\') {
            $path_name = \str_replace(DIRECTORY_SEPARATOR, '/', $path_name);
        }
        return \preg_match($exclude_file_regex, $path_name) > 0;
    }

    // Bound the percentage to [0, 1]
    private static function boundPercentage(float $p) : float
    {
        return \min(\max($p, 0.0), 1.0);
    }

    /**
     * Update a progress bar on the screen
     *
     * @param string $msg
     * A short message to display with the progress
     * meter
     *
     * @param float $p
     * The percentage to display
     *
     * @param ?(string|FQSEN|AddressableElement) $details
     * Details about what is being analyzed within the phase for $msg
     *
     * @return void
     */
    public static function progress(
        string $msg,
        float $p,
        $details = null
    ) {
        if (self::shouldShowDebugOutput()) {
            self::debugProgress($msg, $p, $details);
            return;
        }
        if (!self::shouldShowProgress()) {
            return;
        }

        // Bound the percentage to [0, 1]
        $p = self::boundPercentage($p);

        static $previous_update_time = 0.0;
        $time = \microtime(true);


        // If not enough time has elapsed, then don't update the progress bar.
        // Making the update frequency based on time (instead of the number of files)
        // prevents the terminal from rapidly flickering while processing small files.
        if ($time - $previous_update_time < Config::getValue('progress_bar_sample_interval')) {
            // Make sure to output 100% if this is one of the last phases, to avoid confusion.
            // https://github.com/phan/phan/issues/2694
            if ($p < 1.0 || in_array($msg, ['parse', 'method', 'function'], true)) {
                return;
            }
        }
        $previous_update_time = $time;

        $memory = \memory_get_usage() / 1024 / 1024;
        $peak = \memory_get_peak_usage() / 1024 / 1024;

        $left_side = \str_pad($msg, 10, ' ', STR_PAD_LEFT) .  ' ';
        $right_side =
               " " . \sprintf("%1$ 3d", (int)(100 * $p)) . "%" .
               \sprintf(' %0.2dMB/%0.2dMB', $memory, $peak);

        static $columns = null;
        if ($columns === null) {
            // Only call this once per process, since it can be rather expensive
            $columns = (new Terminal())->getWidth();
        }
        // strlen("  99% 999MB/999MB") == 17
        $used_length = strlen($left_side) + \max(17, strlen($right_side));
        $remaining_length = $columns - $used_length;
        $remaining_length = \min(60, \max(0, $remaining_length));
        if ($remaining_length > 0) {
            $progress_bar = self::renderInnerProgressBar($remaining_length, $p);
        } else {
            $progress_bar = '';
            $right_side = \ltrim($right_side);
        }

        // Build up a string, then make a single call to fwrite(). Should be slightly faster and smoother to render to the console.
        $msg = $left_side .
               $progress_bar .
               $right_side .
               "\r";
        \fwrite(STDERR, $msg);
    }

    /**
     * Print an end to progress bars or debug output
     * @return void
     */
    public static function endProgressBar()
    {
        static $did_end = false;
        if ($did_end) {
            // Overkill as a sanity check
            return;
        }
        $did_end = true;
        if (self::shouldShowDebugOutput()) {
            \fwrite(STDERR, "Phan's analysis is complete\n");
            return;
        }
        if (self::shouldShowProgress()) {
            // Print a newline to stderr to visuall separate stderr from stdout
            \fwrite(STDERR, \PHP_EOL);
            \fflush(\STDOUT);
        }
    }

    /**
     * @return void
     * @param ?(string|FQSEN|AddressableElement) $details
     */
    public static function debugProgress(string $msg, float $p, $details)
    {
        $pct = \sprintf("%d%%", (int)(100 * self::boundPercentage($p)));

        if ($details === null) {
            return;
        }
        if ($details instanceof AddressableElement) {
            $details = $details->getFQSEN();
        }
        switch ($msg) {
            case 'parse':
            case 'analyze':
                $line = "Going to $msg '$details' ($pct)";
                break;
            case 'method':
            case 'function':
                $line = "Going to analyze $msg $details() ($pct)";
                break;
            default:
                $line = "In $msg phase, processing '$details' ($pct)";
                break;
        }
        self::debugOutput($line);
    }

    /**
     * @return void
     */
    public static function debugOutput(string $line)
    {
        \fwrite(STDERR, $line . "\n");
    }

    /**
     * Renders a unicode progress bar that goes from light (left) to dark (right)
     * The length in the console is the positive integer $length
     * @see https://en.wikipedia.org/wiki/Block_Elements
     */
    private static function renderInnerProgressBar(int $length, float $p) : string
    {
        $current_float = $p * $length;
        $current = (int)$current_float;
        $rest = \max($length - $current, 0);

        if (!self::doesTerminalSupportUtf8()) {
            // Show a progress bar of "XXXX>------" in Windows when utf-8 is unsupported.
            $progress_bar = str_repeat("X", $current);
            $delta = $current_float - $current;
            if ($delta > 0.5) {
                $progress_bar .= ">" . str_repeat("-", $rest - 1);
            } else {
                $progress_bar .= str_repeat("-", $rest);
            }
            return $progress_bar;
        }
        // The left-most characters are "Light shade"
        $progress_bar = str_repeat("\u{2588}", $current);
        $delta = $current_float - $current;
        if ($delta > 1.0 / 3) {
            // The between character is "Full block" or "Medium shade" or "solid shade".
            // The remaining characters on the right are "Full block" (darkest)
            $first = $delta > 2.0 / 3 ? "\u{2593}" : "\u{2592}";
            $progress_bar .= $first . str_repeat("\u{2591}", $rest - 1);
        } else {
            $progress_bar .= str_repeat("\u{2591}", $rest);
        }
        return $progress_bar;
    }

    /**
     * Guess if the terminal supports utf-8.
     * In some locales, windows is set to a non-utf-8 codepoint.
     *
     * @see https://github.com/phan/phan/issues/2572
     * @see https://en.wikipedia.org/wiki/Code_page#Windows_code_pages
     * @suppress PhanUndeclaredFunction, UnusedSuppression the function exists only in Windows.
     */
    public static function doesTerminalSupportUtf8() : bool
    {
        if (\strtoupper(\substr(PHP_OS, 0, 3)) === 'WIN') {
            if (!\function_exists('sapi_windows_cp_is_utf8') || !sapi_windows_cp_is_utf8()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Look for a `.phan/config` file up to a few directories
     * up the hierarchy and apply anything in there to
     * the configuration.
     * @throws UsageException
     */
    private function maybeReadConfigFile(bool $require_config_exists)
    {

        // If the file doesn't exist here, try a directory up
        $config_file_name = $this->config_file;
        $config_file_name =
            $config_file_name
            ? \realpath($config_file_name)
            : \implode(DIRECTORY_SEPARATOR, [
                Config::getProjectRootDirectory(),
                '.phan',
                'config.php'
            ]);

        // Totally cool if the file isn't there
        if ($config_file_name === false || !\file_exists($config_file_name)) {
            if ($require_config_exists) {
                // But if the CLI option --require-config-exists is provided, exit immediately.
                // (Include extended help documenting that option)
                if ($config_file_name !== false) {
                    throw new UsageException("Could not find a config file at '$config_file_name', but --require-config-exists was set", EXIT_FAILURE, true);
                } else {
                    $msg = \sprintf(
                        "Could not figure out the path for config file %s, but --require-config-exists was set",
                        StringUtil::encodeValue($this->config_file)
                    );
                    throw new UsageException($msg, EXIT_FAILURE, true);
                }
            }
            return;
        }

        // Read the configuration file
        $config = require($config_file_name);

        // Write each value to the config
        foreach ($config as $key => $value) {
            Config::setValue($key, $value);
        }
    }

    /**
     * This will assert that ast\parse_code or a polyfill can be called.
     * @return void
     * @throws AssertionError on failure
     */
    private static function ensureASTParserExists()
    {
        if (Config::getValue('use_polyfill_parser')) {
            return;
        }
        if (!\extension_loaded('ast')) {
            \fwrite(
                STDERR,
                // phpcs:ignore Generic.Files.LineLength.MaxExceeded
                "The php-ast extension must be loaded in order for Phan to work. See https://github.com/phan/phan#getting-started for more details. Alternately, invoke Phan with the CLI option --allow-polyfill-parser (which is noticeably slower)\n"
            );
            exit(EXIT_FAILURE);
        }

        try {
            // Split up the opening PHP tag to fix highlighting in vim.
            \ast\parse_code(
                '<' . '?php 42;',
                Config::AST_VERSION
            );
        } catch (\LogicException $_) {
            \fwrite(
                STDERR,
                'Unknown AST version ('
                . Config::AST_VERSION
                . ') in configuration. '
                . "You may need to rebuild the latest version of the php-ast extension.\n"
                . "(You are using php-ast " . (new ReflectionExtension('ast'))->getVersion() . ". Alternately, test with --force-polyfill-parser (which is noticeably slower))\n"
            );
            exit(EXIT_FAILURE);
        }

        // Workaround for https://github.com/nikic/php-ast/issues/79
        try {
            \ast\parse_code(
                '<' . '?php syntaxerror',
                Config::AST_VERSION
            );
            \fwrite(
                STDERR,
                'Expected ast\\parse_code to throw ParseError on invalid inputs. Configured AST version: '
                . Config::AST_VERSION
                . '. '
                . "You may need to rebuild the latest version of the php-ast extension.\n"
            );
            exit(EXIT_FAILURE);
        } catch (\ParseError $_) {
            // error message may validate with locale and version, don't validate that.
        }
    }

    /**
     * Returns a string that can be used to check if dev-master versions changed (approximately).
     *
     * This is useful for checking if caches (e.g. of ASTs) should be invalidated.
     */
    public static function getDevelopmentVersionId() : string
    {
        $news_path = \dirname(__DIR__) . '/NEWS.md';
        $version = self::PHAN_VERSION;
        if (\file_exists($news_path)) {
            $version .= '-' . \filesize($news_path);
        }
        return $version;
    }
}
