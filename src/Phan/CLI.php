<?php declare(strict_types=1);
namespace Phan;

use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Filter\CategoryIssueFilter;
use Phan\Output\Filter\ChainedIssueFilter;
use Phan\Output\Filter\FileIssueFilter;
use Phan\Output\Filter\MinimumSeverityFilter;
use Phan\Output\PrinterFactory;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class CLI
{
    /**
     * This should be updated to x.y.z-dev after every release, and x.y.z before a release.
     */
    const PHAN_VERSION = '0.10.0-dev';

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @return OutputInterface
     */
    public function getOutput():OutputInterface
    {
        return $this->output;
    }

    /**
     * @var string[]
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
        // still available: g,n,t,u,w
        $opts = getopt(
            "f:m:o:c:k:aeqbr:pid:3:y:l:xj:zhvs:",
            [
                'backward-compatibility-checks',
                'color',
                'dead-code-detection',
                'directory:',
                'dump-ast',
                'dump-parsed-file-list',
                'dump-signatures-file:',
                'exclude-directory-list:',
                'exclude-file:',
                'include-analysis-file-list:',
                'file-list-only:',
                'file-list:',
                'help',
                'ignore-undeclared',
                'minimum-severity:',
                'output-mode:',
                'output:',
                'parent-constructor-required:',
                'progress-bar',
                'project-root-directory:',
                'quick',
                'version',
                'processes:',
                'config-file:',
                'signature-compatibility',
                'print-memory-usage-summary',
                'markdown-issue-messages',
                'disable-plugins',
                'daemonize-socket:',
                'daemonize-tcp-port:',
                'extended-help',
            ]
        );

        if (\array_key_exists('extended-help', $opts ?? [])) {
            $this->usage('', EXIT_SUCCESS, true);  // --help prints help and calls exit(0)
        }
        if (\array_key_exists('h', $opts ?? []) || \array_key_exists('help', $opts ?? [])) {
            $this->usage();  // --help prints help and calls exit(0)
        }
        if (\array_key_exists('v', $opts ?? []) || \array_key_exists('version', $opts ?? [])) {
            printf("Phan %s\n", self::PHAN_VERSION);
            exit(EXIT_SUCCESS);
        }

        // Determine the root directory of the project from which
        // we root all relative paths passed in as args
        Config::setProjectRootDirectory(
            $opts['d'] ?? $opts['project-root-directory'] ?? getcwd()
        );

        // Before reading the config, check for an override on
        // the location of the config file path.
        if (isset($opts['k'])) {
            $this->config_file = $opts['k'];
        } else if (isset($opts['config-file'])) {
            $this->config_file = $opts['config-file'];
        }

        // Now that we have a root directory, attempt to read a
        // configuration file `.phan/config.php` if it exists
        $this->maybeReadConfigFile();

        $this->output = new ConsoleOutput();
        $factory = new PrinterFactory();
        $printer_type = 'text';
        $minimum_severity = Config::getValue('minimum_severity');
        $mask = -1;

        foreach ($opts ?? [] as $key => $value) {
            switch ($key) {
                case 'r':
                case 'file-list-only':
                    // Mark it so that we don't load files through
                    // other mechanisms.
                    $this->file_list_only = true;

                    // Empty out the file list
                    $this->file_list = [];

                    // Intentionally fall through to load the
                    // file list
                case 'f':
                case 'file-list':
                    $file_list = \is_array($value) ? $value : [$value];
                    foreach ($file_list as $file_name) {
                        $file_path = Config::projectPath($file_name);
                        if (is_file($file_path) && is_readable($file_path)) {
                            /** @var string[] */
                            $this->file_list = array_merge(
                                $this->file_list,
                                file(Config::projectPath($file_name), FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)
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
                            $this->file_list = array_merge(
                                $this->file_list,
                                $this->directoryNameToFileList(
                                    $directory_name
                                )
                            );
                        }
                    }
                    break;
                case 'k':
                case 'config-file':
                    break;
                case 'm':
                case 'output-mode':
                    if (!in_array($value, $factory->getTypes(), true)) {
                        $this->usage(
                            sprintf(
                                'Unknown output mode "%s". Known values are [%s]',
                                $value,
                                implode(',', $factory->getTypes())
                            ),
                            EXIT_FAILURE
                        );
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
                case 'd':
                case 'project-root-directory':
                    // We handle this flag before parsing options so
                    // that we can get the project root directory to
                    // base other config flags values on
                    break;
                case 'disable-plugins':
                    // Slightly faster, e.g. for daemon mode with lowest latency (along with --quick).
                    Config::setValue('plugins', []);
                    break;
                case 's':
                case 'daemonize-socket':
                    $this->checkCanDaemonize('unix');
                    $socket_dirname = realpath(dirname($value));
                    if (!file_exists($socket_dirname) || !is_dir($socket_dirname)) {
                        $msg = sprintf('Requested to create unix socket server in %s, but folder %s does not exist', json_encode($value), json_encode($socket_dirname));
                        $this->usage($msg, 1);
                    } else {
                        Config::setValue('daemonize_socket', $value);  // Daemonize. Assumes the file list won't change. Accepts requests over a Unix socket, or some other IPC mechanism.
                    }
                    break;
                    // TODO: HTTP server binding to 127.0.0.1, daemonize-port.
                case 'daemonize-tcp-port':
                    $this->checkCanDaemonize('tcp');
                    if (strcasecmp($value, 'default') === 0) {
                        $port = 4846;
                    } else {
                        $port = filter_var($value, FILTER_VALIDATE_INT);
                    }
                    if ($port >= 1024 && $port <= 65535) {
                        Config::setValue('daemonize_tcp_port', $port);
                    } else {
                        $this->usage("daemonize-tcp-port must be the string 'default' or a value between 1024 and 65535, got '$value'", 1);
                    }
                    break;
                case 'x':
                case 'dead-code-detection':
                    Config::setValue('dead_code_detection', true);
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
                    $this->usage("Unknown option '-$key'", EXIT_FAILURE);
                    break;
            }
        }

        $printer = $factory->getPrinter($printer_type, $this->output);
        $filter  = new ChainedIssueFilter([
            new FileIssueFilter(new Phan()),
            new MinimumSeverityFilter($minimum_severity),
            new CategoryIssueFilter($mask)
        ]);
        $collector = new BufferingCollector($filter);

        Phan::setPrinter($printer);
        Phan::setIssueCollector($collector);

        $pruneargv = array();
        foreach ($opts ?? [] as $opt => $value) {
            foreach ($argv as $key => $chunk) {
                $regex = '/^'. (isset($opt[1]) ? '--' : '-') . $opt . '/';

                if (($chunk == $value
                    || (\is_array($value) && in_array($chunk, $value))
                    )
                    && $argv[$key-1][0] == '-'
                    || preg_match($regex, $chunk)
                ) {
                    array_push($pruneargv, $key);
                }
            }
        }

        while ($key = array_pop($pruneargv)) {
            unset($argv[$key]);
        }

        foreach ($argv as $arg) {
            if ($arg[0]=='-') {
                $this->usage("Unknown option '{$arg}'", EXIT_FAILURE);
            }
        }

        if (!$this->file_list_only) {
            // Merge in any remaining args on the CLI
            $this->file_list = array_merge(
                $this->file_list,
                array_slice($argv, 1)
            );

            // Merge in any files given in the config
            /** @var string[] */
            $this->file_list = array_merge(
                $this->file_list,
                Config::getValue('file_list')
            );

            // Merge in any directories given in the config
            foreach (Config::getValue('directory_list') as $directory_name) {
                $this->file_list = array_merge(
                    $this->file_list,
                    $this->directoryNameToFileList($directory_name)
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
                $exclude_file_set[$file] = true;
            }

            $this->file_list = array_filter($this->file_list,
                function(string $file) use ($exclude_file_set) : bool {
                    return empty($exclude_file_set[$file]);
                }
            );
        }

        // We can't run dead code detection on multiple cores because
        // we need to update reference lists in a globally accessible
        // way during analysis. With our parallelization mechanism, there
        // is no shared state between processes, making it impossible to
        // have a complete set of reference lists.
        \assert(Config::getValue('processes') === 1
            || !Config::getValue('dead_code_detection'),
            "We cannot run dead code detection on more than one core.");
    }

    /** @return void - exits on usage error */
    private function checkCanDaemonize(string $protocol) {
        $opt = $protocol === 'unix' ? '--daemonize-socket' : '--daemonize-tcp-port';
        if (!in_array($protocol, stream_get_transports())) {
            $this->usage("The $protocol:///path/to/file schema is not supported on this system, cannot create a daemon with $opt", 1);
        }
        if (!function_exists('pcntl_fork')) {
            $this->usage("The pcntl extension is not available to fork a new process, so $opt will not be able to create workers to respond to requests.", 1);
        }
        if (Config::getValue('daemonize_socket') || Config::getValue('daemonize_tcp_port')) {
            $this->usage('Can specify --daemonize-socket or --daemonize-tcp-port only once', 1);
        }
    }

    /**
     * @return string[]
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

        if (!empty($msg)) {
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

 -d, --project-root-directory
  Hunt for a directory named .phan in the current or parent
  directory and read configuration file config.php from that
  path.

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

 --color
  Add colors to the outputted issues. Tested for Unix, recommended for only the default --output-mode ('text')

 -p, --progress-bar
  Show progress bar

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

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
  possibly be removed.

 -j, --processes <int>
  The number of parallel processes to run during the analysis
  phase. Defaults to 1.

 -z, --signature-compatibility
  Analyze signatures for methods that are overrides to ensure
  compatibility with what they're overriding.

 -s, --daemonize-socket </path/to/file.sock>
  Unix socket for Phan to listen for requests on, in daemon mode.

 --daemonize-tcp-port <default|1024-65535>
  TCP port for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for port 4846.)

 -v, --version
  Print phan's version number

 -h, --help
  This help information

 --extended-help
  This help information, plus less commonly used flags

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

 --print-memory-usage-summary
  Emit JSON serialized signatures to the given file.
  This uses a method signature format similar to FunctionSignatureMap.php.

 --markdown-issue-messages
  Emit issue messages with markdown formatting.

EOB;
        }
        exit($exit_code);
    }

    /**
     * @param string $directory_name
     * The name of a directory to scan for files ending in `.php`.
     *
     * @return string[]
     * A list of PHP files in the given directory
     */
    private function directoryNameToFileList(
        string $directory_name
    ) : array {
        $file_list = [];

        try {
            $file_extensions = Config::getValue('analyzed_file_extensions');

            if (!\is_array($file_extensions) || count($file_extensions) === 0) {
                throw new \InvalidArgumentException(
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
                function(\SplFileInfo $file_info) use ($file_extensions, $exclude_file_regex) {
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
        usort($file_list, function(string $a, string $b) : int {
            // Sort lexicographically by paths **within the results for a directory**,
            // to work around some file systems not returning results lexicographically.
            // Keep directories together by replacing directory separators with the null byte
            // (E.g. "a.b" is lexicographically less than "a/b", but "aab" is greater than "a/b")
            return \strcmp(\preg_replace("@[/\\\\]+@", "\0", $a), \preg_replace("@[/\\\\]+@", "\0", $b));
        });

        return $file_list;
    }

    public static function shouldShowProgress() : bool
    {
        return Config::getValue('progress_bar') &&
            !Config::getValue('dump_ast') &&
            !Config::getValue('daemonize_tcp_port') &&
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

        // Don't update every time when we're moving
        // super fast
        if ($p > 0.0
            && $p < 1.0
            && rand(0, 1000) > (1000 * Config::getValue('progress_bar_sample_rate')
            )) {
            return;
        }

        // If we're on windows, just print a dot to show we're
        // working
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            fwrite(STDERR, '.');
            return;
        }
        $memory = memory_get_usage()/1024/1024;
        $peak = memory_get_peak_usage()/1024/1024;

        $current = (int)($p * 60);
        $rest = max(60 - $current, 0);

        // Build up a string, then make a single call to fwrite(). Should be slightly faster and smoother to render to the console.
        $msg = str_pad($msg, 10, ' ', STR_PAD_LEFT) .
               ' ' .
               str_repeat("\u{2588}", $current) .
               str_repeat("\u{2591}", $rest) .
               " " . sprintf("% 3d", (int)(100*$p)) . "%" .
               sprintf(' %0.2dMB/%0.2dMB', $memory, $peak) . "\r";
        fwrite(STDERR, $msg);
    }

    /**
     * Look for a .phan/config file up to a few directories
     * up the hierarchy and apply anything in there to
     * the configuration.
     */
    private function maybeReadConfigFile()
    {

        // If the file doesn't exist here, try a directory up
        $config_file_name =
            !empty($this->config_file)
            ? realpath($this->config_file)
            : implode(DIRECTORY_SEPARATOR, [
                Config::getProjectRootDirectory(),
                '.phan',
                'config.php'
            ]);

        // Totally cool if the file isn't there
        if (!file_exists($config_file_name)) {
            return;
        }

        // Read the configuration file
        $config = require($config_file_name);

        // Write each value to the config
        foreach ($config as $key => $value) {
            Config::setValue($key, $value);
        }
    }
}
