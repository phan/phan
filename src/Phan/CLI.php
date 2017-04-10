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
    const PHAN_VERSION = '0.9.2-dev';

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
            "f:m:o:c:k:aeqbr:pid:3:y:l:xj:zh:v",
            [
                'backward-compatibility-checks',
                'dead-code-detection',
                'directory:',
                'dump-ast',
                'dump-signatures-file:',
                'exclude-directory-list:',
                'exclude-file:',
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
                'markdown-issue-messages',
                'extended-help',
            ]
        );

        if (array_key_exists('extended-help', $opts ?? [])) {
            $this->usage('', EXIT_SUCCESS, true);  // --help prints help and calls exit(0)
        }
        if (array_key_exists('h', $opts ?? []) || array_key_exists('help', $opts ?? [])) {
            $this->usage();  // --help prints help and calls exit(0)
        }
        if (array_key_exists('v', $opts ?? []) || array_key_exists('version', $opts ?? [])) {
            printf("Phan %s\n", self::PHAN_VERSION);
            exit(EXIT_SUCCESS);
        }

        // Determine the root directory of the project from which
        // we root all relative paths passed in as args
        Config::get()->setProjectRootDirectory(
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
        $minimum_severity = Config::get()->minimum_severity;
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
                    $file_list = is_array($value) ? $value : [$value];
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
                        $directory_list = is_array($value) ? $value : [$value];
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
                    Config::get()->parent_constructor_required =
                    explode(',', $value);
                    break;
                case 'q':
                case 'quick':
                    Config::get()->quick_mode = true;
                    break;
                case 'b':
                case 'backward-compatibility-checks':
                    Config::get()->backward_compatibility_checks = true;
                    break;
                case 'p':
                case 'progress-bar':
                    Config::get()->progress_bar = true;
                    break;
                case 'a':
                case 'dump-ast':
                    Config::get()->dump_ast = true;
                    break;
                case 'dump-signatures-file':
                    Config::get()->dump_signatures_file = $value;
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
                    Config::get()->exclude_analysis_directory_list = explode(',', $value);
                    break;
                case 'exclude-file':
                    Config::get()->exclude_file_list = array_merge(
                        Config::get()->exclude_file_list,
                        is_array($value) ? $value : [$value]
                    );
                    break;
                case 'j':
                case 'processes':
                    Config::get()->processes = (int)$value;
                    break;
                case 'z':
                case 'signature-compatibility':
                    Config::get()->analyze_signature_compatibility = (bool)$value;
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
                case 'x':
                case 'dead-code-detection':
                    Config::get()->dead_code_detection = true;
                    break;
                case 'markdown-issue-messages':
                    Config::get()->markdown_issue_messages = true;
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
                    || (is_array($value) && in_array($chunk, $value))
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
                Config::get()->file_list
            );

            // Merge in any directories given in the config
            foreach (Config::get()->directory_list as $directory_name) {
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
        if (count(Config::get()->exclude_file_list) > 0) {
            $exclude_file_set = [];
            foreach (Config::get()->exclude_file_list as $file) {
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
        assert(Config::get()->processes === 1
            || !Config::get()->dead_code_detection,
            "We cannot run dead code detection on more than one core.");
    }

    /**
     * @return string[]
     * Get the set of files to analyze
     */
    public function getFileList() : array
    {
        return $this->file_list;
    }

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

 --dump-signatures-file <filename>
  Emit JSON serialized signatures to the given file.
  This uses a method signature format similar to FunctionSignatureMap.php.

 --markdown-issue-messages
  Emit issue messages with markdown formatting.

EOB;
        }
        exit($exit_code);
    }

    public static function shouldShowProgress() : bool
    {
        $config = Config::get();
        return $config->progress_bar
            && !$config->dump_ast;
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
            $file_extensions = Config::get()->analyzed_file_extensions;

            if (!is_array($file_extensions) || count($file_extensions) === 0) {
                throw new \InvalidArgumentException(
                    'Empty list in config analyzed_file_extensions. Nothing to analyze.'
                );
            }

            $exclude_file_regex = Config::get()->exclude_file_regex;
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

                    if ($exclude_file_regex && preg_match($exclude_file_regex,$file_info->getBasename())) {
                        return false;
                    }

                    return true;
                }
            );

            $file_list = array_keys(iterator_to_array($iterator));
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        return $file_list;
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
     * @param float $sample_rate
     * How frequently we should update the progress
     * bar, randomly sampled
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

        if (!Config::get()->progress_bar || Config::get()->dump_ast) {
            return;
        }

        // Don't update every time when we're moving
        // super fast
        if ($p > 0.0
            && $p < 1.0
            && rand(0, 1000) > (1000 * Config::get()->progress_bar_sample_rate
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
                Config::get()->getProjectRootDirectory(),
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
            Config::get()->__set($key, $value);
        }
    }
}
