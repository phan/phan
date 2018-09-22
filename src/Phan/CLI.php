<?php declare(strict_types=1);
namespace Phan;

use AssertionError;
use InvalidArgumentException;
use Phan\Config\Initializer;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Filter\CategoryIssueFilter;
use Phan\Output\Filter\ChainedIssueFilter;
use Phan\Output\Filter\FileIssueFilter;
use Phan\Output\Filter\MinimumSeverityFilter;
use Phan\Output\PrinterFactory;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Contains methods for parsing CLI arguments to Phan,
 * outputting to the CLI, as well as helper methods to retrieve files/folders
 * for the analyzed project.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal
 */
class CLI
{
    /**
     * This should be updated to x.y.z-dev after every release, and x.y.z before a release.
     */
    const PHAN_VERSION = '1.0.6-dev';

    /**
     * List of short flags passed to getopt
     * still available: g,n,t,u,w
     * @internal
     */
    const GETOPT_SHORT_OPTIONS = 'f:m:o:c:k:aeqbr:pid:3:y:l:xj:zhvs:';

    /**
     * List of long flags passed to getopt
     * @internal
     */
    const GETOPT_LONG_OPTIONS = [
        'allow-polyfill-parser',
        'backward-compatibility-checks',
        'color',
        'config-file:',
        'daemonize-socket:',
        'daemonize-tcp-host:',
        'daemonize-tcp-port:',
        'dead-code-detection',
        'directory:',
        'disable-plugins',
        'dump-ast',
        'dump-parsed-file-list',
        'dump-signatures-file:',
        'exclude-directory-list:',
        'exclude-file:',
        'extended-help',
        'file-list:',
        'file-list-only:',
        'force-polyfill-parser',
        'help',
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
        'language-server-hide-category',
        'language-server-allow-missing-pcntl',
        'language-server-force-missing-pcntl',
        'language-server-require-pcntl',
        'language-server-enable',
        'language-server-enable-go-to-definition',
        'language-server-enable-hover',
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
        'strict-param-checking',
        'strict-property-checking',
        'strict-return-checking',
        'strict-type-checking',
        'target-php-version',
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
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     */
    public function __construct()
    {
        global $argv;

        // Parse command line args
        $opts = getopt(self::GETOPT_SHORT_OPTIONS, self::GETOPT_LONG_OPTIONS);
        $opts = $opts ?? [];

        if (\array_key_exists('extended-help', $opts)) {
            $this->usage('', EXIT_SUCCESS, true);  // --help prints help and calls exit(0)
        }
        if (\array_key_exists('h', $opts) || \array_key_exists('help', $opts)) {
            $this->usage();  // --help prints help and calls exit(0)
        }
        if (\array_key_exists('v', $opts ?? []) || \array_key_exists('version', $opts ?? [])) {
            printf("Phan %s\n", self::PHAN_VERSION);
            exit(EXIT_SUCCESS);
        }

        // Determine the root directory of the project from which
        // we route all relative paths passed in as args
        $overridden_project_root_directory = $opts['d'] ?? $opts['project-root-directory'] ?? null;
        if (\is_string($overridden_project_root_directory)) {
            if (!\is_dir($overridden_project_root_directory)) {
                $this->usage(\json_encode($overridden_project_root_directory) . ' is not a directory', EXIT_FAILURE);
            }
            // Set the current working directory so that relative paths within the project will work.
            // TODO: Add an option to allow searching ancestor directories?
            \chdir($overridden_project_root_directory);
        }
        $cwd = \getcwd();
        if (!is_string($cwd)) {
            echo "Failed to find current working directory\n";
            exit(1);
        }
        Config::setProjectRootDirectory($cwd);

        if (\array_key_exists('init', $opts)) {
            $exit_code = Initializer::initPhanConfig($opts);
            if ($exit_code === 0) {
                exit($exit_code);
            }
            echo "\n";
            // --init is currently in --extended-help
            $this->usage('', $exit_code, true);
        }

        // Before reading the config, check for an override on
        // the location of the config file path.
        $config_file_override = $opts['k'] ?? $opts['config-file'] ?? null;
        if (is_string($config_file_override)) {
            $this->config_file = $config_file_override;
        }

        if (isset($opts['language-server-force-missing-pcntl'])) {
            Config::setValue('language_server_use_pcntl_fallback', true);
        } elseif (!isset($opts['language-server-require-pcntl'])) {
            // --language-server-allow-missing-pcntl is now the default
            if (!extension_loaded('pcntl')) {
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
                            error_log("invalid argument for --file-list");
                            continue;
                        }
                        $file_path = Config::projectPath($file_name);
                        if (is_file($file_path) && is_readable($file_path)) {
                            /** @var array<int,string> */
                            $this->file_list_in_config = array_merge(
                                $this->file_list_in_config,
                                file(Config::projectPath($file_name), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                            );
                        } else {
                            error_log("Unable to read file $file_path");
                        }
                    }
                    break;
                case 'l':
                case 'directory':
                    if (!$this->file_list_only) {
                        $directory_list = \is_array($value) ? $value : [$value];
                        foreach ($directory_list as $directory_name) {
                            if (!is_string($directory_name)) {
                                error_log("Invalid --directory setting");
                                return;
                            }
                            $this->file_list_in_config = array_merge(
                                $this->file_list,
                                array_values($this->directoryNameToFileList(
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
                        $this->usage(
                            sprintf(
                                'Unknown output mode %s. Known values are [%s]',
                                json_encode($value),
                                implode(',', $factory->getTypes())
                            ),
                            EXIT_FAILURE
                        );
                        return;  // unreachable
                    }

                    $printer_type = $value;
                    break;
                case 'c':
                case 'parent-constructor-required':
                    Config::setValue('parent_constructor_required', explode(',', $value));
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
                case 'o':
                case 'output':
                    $this->output = new StreamOutput(fopen($value, 'w'));
                    break;
                case 'i':
                case 'ignore-undeclared':
                    $mask ^= Issue::CATEGORY_UNDEFINED;
                    break;
                case '3':
                case 'exclude-directory-list':
                    Config::setValue('exclude_analysis_directory_list', explode(',', $value));
                    break;
                case 'exclude-file':
                    Config::setValue('exclude_file_list', array_merge(
                        Config::getValue('exclude_file_list'),
                        \is_array($value) ? $value : [$value]
                    ));
                    break;
                case 'include-analysis-file-list':
                    Config::setValue('include_analysis_file_list', explode(',', $value));
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
                case 'disable-plugins':
                    // Slightly faster, e.g. for daemon mode with lowest latency (along with --quick).
                    Config::setValue('plugins', []);
                    break;
                case 'plugin':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    Config::setValue(
                        'plugins',
                        array_unique(array_merge(Config::getValue('plugins'), $value))
                    );
                    break;
                case 'use-fallback-parser':
                    Config::setValue('use_fallback_parser', true);
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
                case 'strict-type-checking':
                    Config::setValue('strict_param_checking', true);
                    Config::setValue('strict_property_checking', true);
                    Config::setValue('strict_return_checking', true);
                    break;
                case 's':
                case 'daemonize-socket':
                    $this->checkCanDaemonize('unix', $key);
                    $socket_dirname = realpath(dirname($value));
                    if (!file_exists($socket_dirname) || !is_dir($socket_dirname)) {
                        $msg = sprintf('Requested to create unix socket server in %s, but folder %s does not exist', json_encode($value), json_encode($socket_dirname));
                        $this->usage($msg, 1);
                    } else {
                        Config::setValue('daemonize_socket', $value);  // Daemonize. Assumes the file list won't change. Accepts requests over a Unix socket, or some other IPC mechanism.
                    }
                    break;
                    // TODO(possible idea): HTTP server binding to 127.0.0.1, daemonize-http-port.
                case 'daemonize-tcp-host':
                    $this->checkCanDaemonize('tcp', $key);
                    Config::setValue('daemonize_tcp', true);
                    $host = filter_var($value, FILTER_VALIDATE_IP);
                    if (strcasecmp($value, 'default') !== 0 && !$host) {
                        $this->usage("daemonize-tcp-host must be the string 'default' or a valid hostname, got '$value'", 1);
                    }
                    if ($host) {
                        Config::setValue('daemonize_tcp_host', $host);
                    }
                    break;
                case 'daemonize-tcp-port':
                    $this->checkCanDaemonize('tcp', $key);
                    Config::setValue('daemonize_tcp', true);
                    $port = filter_var($value, FILTER_VALIDATE_INT);
                    if (strcasecmp($value, 'default') !== 0 && !($port >= 1024 && $port <= 65535)) {
                        $this->usage("daemonize-tcp-port must be the string 'default' or a value between 1024 and 65535, got '$value'", 1);
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
                case 'language-server-enable-go-to-definition':
                    Config::setValue('language_server_enable_go_to_definition', true);
                    break;
                case 'language-server-enable-hover':
                    Config::setValue('language_server_enable_hover', true);
                    break;
                case 'language-server-verbose':
                    Config::setValue('language_server_debug_level', 'info');
                    break;
                case 'x':
                case 'dead-code-detection':
                    Config::setValue('dead_code_detection', true);
                    break;
                case 'unused-variable-detection':
                    Config::setValue('unused_variable_detection', true);
                    break;
                case 'allow-polyfill-parser':
                    // Just check if it's installed and of a new enough version.
                    // Assume that if there is an installation, it works, and warn later in ensureASTParserExists()
                    if (!extension_loaded('ast')) {
                        Config::setValue('use_polyfill_parser', true);
                        break;
                    }
                    if (version_compare((new \ReflectionExtension('ast'))->getVersion(), '0.1.5') < 0) {
                        Config::setValue('use_polyfill_parser', true);
                        break;
                    }
                    break;
                case 'force-polyfill-parser':
                    Config::setValue('use_polyfill_parser', true);
                    break;
                case 'memory-limit':
                    if (preg_match('@^([1-9][0-9]*)([KMG])?$@', $value, $match)) {
                        ini_set('memory_limit', $value);
                    } else {
                        fwrite(STDERR, "Invalid --memory-limit '$value', ignoring\n");
                    }
                    break;
                case 'print-memory-usage-summary':
                    Config::setValue('print_memory_usage_summary', true);
                    break;
                case 'markdown-issue-messages':
                    Config::setValue('markdown_issue_messages', true);
                    break;
                case 'color':
                    Config::setValue('color_issue_messages', true);
                    break;
                default:
                    $this->usage("Unknown option '-$key'" . self::getFlagSuggestionString($key), EXIT_FAILURE);
                    break;
            }
        }

        $this->ensureASTParserExists();

        $output = $this->output;
        $printer = $factory->getPrinter($printer_type, $output);
        $filter  = new ChainedIssueFilter([
            new FileIssueFilter(new Phan()),
            new MinimumSeverityFilter($minimum_severity),
            new CategoryIssueFilter($mask)
        ]);
        $collector = new BufferingCollector($filter);

        Phan::setPrinter($printer);
        Phan::setIssueCollector($collector);

        $pruneargv = [];
        foreach ($opts as $opt => $value) {
            foreach ($argv as $key => $chunk) {
                $regex = '/^' . (isset($opt[1]) ? '--' : '-') . $opt . '/';

                if (in_array($chunk, is_array($value) ? $value : [$value])
                    && $argv[$key - 1][0] == '-'
                    || preg_match($regex, $chunk)
                ) {
                    $pruneargv[] = $key;
                }
            }
        }

        while (count($pruneargv) > 0) {
            $key = array_pop($pruneargv);
            unset($argv[$key]);
        }

        foreach ($argv as $arg) {
            if ($arg[0] == '-') {
                $this->usage("Unknown option '{$arg}'", EXIT_FAILURE);
            }
        }
        if (!$this->file_list_only) {
            // Merge in any remaining args on the CLI
            $this->file_list_in_config = array_merge(
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

    /**
     * @return void
     */
    public function recomputeFileList()
    {
        $this->file_list = $this->file_list_in_config;

        if (!$this->file_list_only) {
            // Merge in any files given in the config
            /** @var array<int,string> */
            $this->file_list = array_merge(
                $this->file_list,
                Config::getValue('file_list')
            );

            // Merge in any directories given in the config
            foreach (Config::getValue('directory_list') as $directory_name) {
                $this->file_list = array_merge(
                    $this->file_list,
                    array_values($this->directoryNameToFileList($directory_name))
                );
            }

            // Don't scan anything twice
            $this->file_list = array_unique($this->file_list);
        }

        // Exclude any files that should be excluded from
        // parsing and analysis (not read at all)
        if (count(Config::getValue('exclude_file_list')) > 0) {
            $exclude_file_set = [];
            foreach (Config::getValue('exclude_file_list') as $file) {
                $normalized_file = str_replace('\\', '/', $file);
                $exclude_file_set[$normalized_file] = true;
                $exclude_file_set["./$normalized_file"] = true;
            }

            $this->file_list = array_filter(
                $this->file_list,
                function (string $file) use ($exclude_file_set) : bool {
                    // Handle edge cases such as 'mydir/subdir\subsubdir' on Windows, if mydir/subdir was in the Phan config.
                    return !isset($exclude_file_set[\str_replace('\\', '/', $file)]);
                }
            );
        }
    }

    /** @return void - exits on usage error */
    private function checkCanDaemonize(string $protocol, string $opt)
    {
        $opt = strlen($opt) >= 2 ? "--$opt" : "-$opt";
        if (!in_array($protocol, stream_get_transports())) {
            $this->usage("The $protocol:///path/to/file schema is not supported on this system, cannot create a daemon with $opt", 1);
        }
        if (!Config::getValue('language_server_use_pcntl_fallback') && !function_exists('pcntl_fork')) {
            $this->usage("The pcntl extension is not available to fork a new process, so $opt will not be able to create workers to respond to requests.", 1);
        }
        if ($opt === '--daemonize-socket' && Config::getValue('daemonize_tcp')) {
            $this->usage('Can specify --daemonize-socket or --daemonize-tcp-port only once', 1);
        } elseif (($opt === '--daemonize-tcp-host' || $opt === '--daemonize-tcp-port') && Config::getValue('daemonize_socket')) {
            $this->usage("Can specify --daemonize-socket or $opt only once", 1);
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

    // FIXME: If I stop using defined() in UnionTypeVisitor,
    // this will warn about the undefined constant EXIT_SUCCESS when a
    // user-defined constant is used in parse phase in a function declaration
    private function usage(string $msg = '', int $exit_code = EXIT_SUCCESS, bool $print_extended_help = false)
    {
        global $argv;

        if ($msg !== '') {
            echo "$msg\n";
        }

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

  Generally, you'll want to include the directories for
  third-party code (such as "vendor/") in this list.

 --include-analysis-file-list <file_list>
  A comma-separated list of files that will be included in
  static analysis. All others won't be analyzed.

  This is primarily intended for performing standalone
  incremental analysis.

 -d, --project-root-directory </path/to/project>
  Hunt for a directory named .phan in the provided directory
  and read configuration file .phan/config.php from that path.

 -r, --file-list-only
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is unlikely to be useful.

 -k, --config-file
  A path to a config file to load (instead of the default of
  .phan/config.php).

 -m <mode>, --output-mode
  Output mode from 'text', 'json', 'csv', 'codeclimate', 'checkstyle', or 'pylint'

 -o, --output <filename>
  Output filename

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

 --color
  Add colors to the outputted issues. Tested in Unix.
  This is recommended for only the default --output-mode ('text')

 -p, --progress-bar
  Show progress bar

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

 --target-php-version {7.0,7.1,7.2,7.3,native}
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
  possibly be removed. This implies `--unused-variable-detection`.

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

 --disable-plugins
  Don't run any plugins. Slightly faster.

 --plugin <pluginName|path/to/Plugin.php>
  Add an additional plugin to run. This flag can be repeated.
  (Either pass the name of the plugin or a relative/absolute path to the plugin)

 --strict-param-checking
  Enables the config option `strict_param_checking`.

 --strict-property-checking
  Enables the config option `strict_property_checking`.

 --strict-return-checking
  Enables the config option `strict_return_checking`.

 --strict-type-checking
  Equivalent to
  `--strict-param-checking --strict-property-checking --strict-return-checking`.

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

 --language-server-enable-go-to-definition
  Enables support for "Go To Definition" and "Go To Type Definition" in the Phan Language Server.
  Disabled by default.

 --language-server-enable-hover
  Enables support for "Hover" in the Phan Language Server.
  Disabled by default.

 --language-server-verbose
  Emit verbose logging messages related to the language server implementation to stderr.
  This is useful when developing or debugging language server clients.

 --language-server-allow-missing-pcntl
  Noop (This is the default behavior).
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
  Exit immediately with an error code if .phan/config.php does not exist.

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
        $trim = function (string $s) : string {
            return rtrim($s, ':');
        };
        $generate_suggestion = function (string $suggestion) : string {
            return (strlen($suggestion) === 1 ? '-' : '--') . $suggestion;
        };
        $generate_suggestion_text = function (string $suggestion, string ...$other_suggestions) use ($generate_suggestion) : string {
            $suggestions = array_merge([$suggestion], $other_suggestions);
            return ' (did you mean ' . implode(' or ', array_map($generate_suggestion, $suggestions)) . '?)';
        };
        $short_options = array_filter(array_map($trim, str_split(self::GETOPT_SHORT_OPTIONS)));
        if (strlen($key) === 1) {
            $alternate = ctype_lower($key) ? strtoupper($key) : strtolower($key);
            if (in_array($alternate, $short_options)) {
                return $generate_suggestion_text($alternate);
            }
            return '';
        } elseif ($key === '') {
            return '';
        }
        // include short options in case a typo is made like -aa instead of -a
        $known_flags = array_merge(self::GETOPT_LONG_OPTIONS, $short_options);

        $known_flags = array_map($trim, $known_flags);

        $similarities = [];

        $key_lower = strtolower($key);
        foreach ($known_flags as $flag) {
            if (strlen($flag) === 1 && stripos($key, $flag) === false) {
                // Skip over suggestions of flags that have no common characters
                continue;
            }
            $distance = levenshtein($key_lower, strtolower($flag));
            // distance > 5 is to far off to be a typo
            if ($distance <= 5) {
                $similarities[$flag] = $distance;
            }
        }

        asort($similarities); // retain keys and sort descending
        $similar_flags = array_keys($similarities);
        $similarity_values = array_values($similarities);

        if (count($similar_flags) >= 2 && ($similarity_values[1] <= $similarity_values[0] + 1)) {
            // If the next-closest suggestion isn't close to as similar as the closest suggestion, just return the closest suggestion
            return $generate_suggestion_text($similar_flags[0], $similar_flags[1]);
        } elseif (count($similar_flags) >= 1) {
            return $generate_suggestion_text($similar_flags[0]);
        }
        return '';
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
    private function directoryNameToFileList(
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
                function (\SplFileInfo $file_info) use ($file_extensions, $exclude_file_regex) : bool {
                    if (!in_array($file_info->getExtension(), $file_extensions, true)) {
                        return false;
                    }

                    if (!$file_info->isFile() || !$file_info->isReadable()) {
                        $file_path = $file_info->getRealPath();
                        error_log("Unable to read file {$file_path}");
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

            $file_list = array_keys(iterator_to_array($iterator));
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        // Normalize leading './' in paths.
        $normalized_file_list = [];
        foreach ($file_list as $file_path) {
            $file_path = preg_replace('@^(\.[/\\\\]+)+@', '', $file_path);
            $normalized_file_list[$file_path] = $file_path;
        }
        usort($normalized_file_list, function (string $a, string $b) : int {
            // Sort lexicographically by paths **within the results for a directory**,
            // to work around some file systems not returning results lexicographically.
            // Keep directories together by replacing directory separators with the null byte
            // (E.g. "a.b" is lexicographically less than "a/b", but "aab" is greater than "a/b")
            return \strcmp(\preg_replace("@[/\\\\]+@", "\0", $a), \preg_replace("@[/\\\\]+@", "\0", $b));
        });

        return $normalized_file_list;
    }

    public static function shouldShowProgress() : bool
    {
        return Config::getValue('progress_bar') &&
            !Config::getValue('dump_ast') &&
            !Config::getValue('daemonize_tcp') &&
            !Config::getValue('daemonize_socket');
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
        // Make this behave the same way on linux/unix and on Windows.
        if (DIRECTORY_SEPARATOR === '\\') {
            $path_name = str_replace(DIRECTORY_SEPARATOR, '/', $path_name);
        }
        return preg_match($exclude_file_regex, $path_name) > 0;
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
     * @return void
     */
    public static function progress(
        string $msg,
        float $p
    ) {
        if (!self::shouldShowProgress()) {
            return;
        }

        // Bound the percentage to [0, 1]
        $p = min(max($p, 0.0), 1.0);

        static $previous_update_time = 0.0;
        $time = microtime(true);


        // If not enough time has elapsed, then don't update the progress bar.
        // Making the update frequency based on time (instead of the number of files)
        // prevents the terminal from rapidly flickering while processing small files.
        if ($time - $previous_update_time < Config::getValue('progress_bar_sample_interval')) {
            return;
        }
        $previous_update_time = $time;

        // If we're on windows, just print a dot to show we're
        // working
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            fwrite(STDERR, '.');
            return;
        }
        $memory = memory_get_usage() / 1024 / 1024;
        $peak = memory_get_peak_usage() / 1024 / 1024;

        $current = (int)($p * 60);
        $rest = max(60 - $current, 0);

        // Build up a string, then make a single call to fwrite(). Should be slightly faster and smoother to render to the console.
        $msg = str_pad($msg, 10, ' ', STR_PAD_LEFT) .
               ' ' .
               str_repeat("\u{2588}", $current) .
               str_repeat("\u{2591}", $rest) .
               " " . sprintf("%1$ 3d", (int)(100 * $p)) . "%" .
               sprintf(' %0.2dMB/%0.2dMB', $memory, $peak) . "\r";
        fwrite(STDERR, $msg);
    }

    /**
     * Look for a .phan/config file up to a few directories
     * up the hierarchy and apply anything in there to
     * the configuration.
     */
    private function maybeReadConfigFile(bool $require_config_exists)
    {

        // If the file doesn't exist here, try a directory up
        $config_file_name =
            $this->config_file
            ? realpath($this->config_file)
            : implode(DIRECTORY_SEPARATOR, [
                Config::getProjectRootDirectory(),
                '.phan',
                'config.php'
            ]);

        // Totally cool if the file isn't there
        if ($config_file_name === false || !file_exists($config_file_name)) {
            if ($require_config_exists) {
                // But if the CLI option --require-config-exists is provided, exit immediately.
                // (Include extended help documenting that option)
                if ($config_file_name !== false) {
                    $this->usage("Could not find a config file at '$config_file_name', but --require-config-exists was set", EXIT_FAILURE, true);
                } else {
                    $this->usage(sprintf("Could not figure out the path for config file '%s', but --require-config-exists was set", $this->config_file), EXIT_FAILURE, true);
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
    private function ensureASTParserExists()
    {
        if (Config::getValue('use_polyfill_parser')) {
            return;
        }
        if (!extension_loaded('ast')) {
            fwrite(
                STDERR,
                // phpcs:ignore Generic.Files.LineLength.MaxExceeded
                'The php-ast extension must be loaded in order for Phan to work. See https://github.com/phan/phan#getting-it-running for more details. Alternately, invoke Phan with the CLI option --allow-polyfill-parser (which is noticeably slower)'
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
            fwrite(
                STDERR,
                'Unknown AST version ('
                . Config::AST_VERSION
                . ') in configuration. '
                . 'You may need to rebuild the latest '
                . 'version of the php-ast extension.'
            );
            exit(EXIT_FAILURE);
        }

        // Workaround for https://github.com/nikic/php-ast/issues/79
        try {
            \ast\parse_code(
                '<' . '?php syntaxerror',
                Config::AST_VERSION
            );
            fwrite(
                STDERR,
                'Expected ast\\parse_code to throw ParseError on invalid inputs. Configured AST version: '
                . Config::AST_VERSION
                . '. '
                . 'You may need to rebuild the latest '
                . 'version of the php-ast extension.'
            );
            exit(EXIT_FAILURE);
        } catch (\ParseError $_) {
            // error message may validate with locale and version, don't validate that.
        }
    }
}
