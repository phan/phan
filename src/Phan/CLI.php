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
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     */
    public function __construct()
    {
        global $argv;

        // Parse command line args
        // still available: g,j,k,n,t,u,v,w,z
        $opts = getopt(
            "f:m:o:c:aeqbr:pid:s:3:y:l:xh::",
            [
                'backward-compatibility-checks',
                'dead-code-detection',
                'directory:',
                'dump-ast',
                'exclude-directory-list:',
                'expand-file-list',
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
                'state-file:',
                'find-refs:',
            ]
        );

        // Determine the root directory of the project from which
        // we root all relative paths passed in as args
        Config::get()->setProjectRootDirectory(
            $opts['d'] ?? getcwd()
        );

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
                case 'h':
                case 'help':
                    $this->usage();
                    break;
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
                        if (is_file($file_name) && is_readable($file_name)) {
                            $this->file_list = array_merge(
                                $this->file_list,
                                file($file_name, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)
                            );
                        } else {
                            error_log("Unable to read file $file_name");
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
                case 'm':
                case 'output-mode':
                    if (!in_array($value, $factory->getTypes(), true)) {
                        $this->usage(
                            sprintf(
                                'Unknown output mode "%s". Known values are [%s]',
                                $value,
                                implode(',', $factory->getTypes())
                            )
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
                case 'e':
                case 'expand-file-list':
                    Config::get()->expand_file_list = true;
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
                    Config::get()->exclude_analysis_directory_list =
                    explode(',', $value);
                    break;
                case 's':
                case 'state-file':
                    // TODO: re-enable eventually
                    // Config::get()->stored_state_file_path = $value;
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
                case 'find-refs':
                    if (Config::get()->stored_state_file_path !== null) {
                        Config::get()->find_refs = true;
                        Config::get()->query = $value;
                    } else {
                        $this->usage('In order to find references, a state file must provided with the -s flag');
                    }
                    break;
                default:
                    $this->usage("Unknown option '-$key'");
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
                $this->usage("Unknown option '{$arg}'");
            }
        }


        if (!$this->file_list_only) {

            // Merge in any remaining args on the CLI
            $this->file_list = array_merge(
                $this->file_list,
                array_slice($argv, 1)
            );

            // Merge in any files given in the config
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


    }

    /**
     * @return string[]
     * Get the set of files to analyze
     */
    public function getFileList() : array
    {
        return $this->file_list;
    }

    private function usage(string $msg = '')
    {
        global $argv;

        if (!empty($msg)) {
            echo "$msg\n";
        }

        echo <<<EOB
Usage: {$argv[0]} [options] [files...]
 -f, --file-list <filename>
  A file containing a list of PHP files to be analyzed

 -r, --file-list-only
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is useful when running Phan from a stored state file and
  passing in a small subset of files to be re-analyzed.

 -l, --directory <directory>
  A directory to recursively read PHP files from to analyze

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories for which any files
  therein should be parsed but not analyzed.

 -d, --project-root-directory
  Hunt for a directory named .phan in the current or parent
  directory and read configuration file config.php from that
  path.

 -m <mode>, --output-mode
  Output mode from 'text', 'json', or 'codeclimate'

 -o, --output <filename>
  Output filename

 -p, --progress-bar
  Show progress bar

 -a, --dump-ast
  Emit an AST for each file rather than analyze

 -e, --expand-file-list
  Expand the list of files passed in to include any files
  that depend on elements defined in those files. This is
  useful when running Phan from a state file and passing in
  just the set of changed files.

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

 --find-refs <FQSEN>
  Find all known references to the provided FQSEN
  See https://github.com/etsy/phan/wiki/Developer%27s-Guide-To-Phan#fqsen
  for how to construct a FQSEN.

  Requires that the --state-file flag be set and the target database
  is populated.

 -h,--help
  This help information

EOB;
        exit;
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
            $iterator = new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory_name)
                ),
                '/^.+\.php$/i',
                \RecursiveRegexIterator::GET_MATCH
            );

            foreach (array_keys(iterator_to_array($iterator)) as $file_name) {
                if (is_file($file_name) && is_readable($file_name)) {
                    $file_list[] = $file_name;
                } else {
                    error_log("Unable to read file $file_name");
                }
            }
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
     * @return null
     */
    public static function progress(
        string $msg,
        float $p
    ) {
        // Bound the percentage to [0, 1]
        $p = min(max($p, 0.0), 1.0);

        if (!Config::get()->progress_bar || Config::get()->dump_ast) {
            return;
        }

        // Don't update every time when we're moving
        // super fast
        if ($p < 1.0
            && rand(0, 1000) > (1000 * Config::get()->progress_bar_sample_rate
            )) {
            return;
        }

        $memory = memory_get_usage()/1024/1024;
        $peak = memory_get_peak_usage()/1024/1024;

        $padded_message = str_pad($msg, 10, ' ', STR_PAD_LEFT);

        fwrite(STDERR, "$padded_message ");
        $current = (int)($p * 60);
        $rest = max(60 - $current, 0);
        fwrite(STDERR, str_repeat("\u{2588}", $current));
        fwrite(STDERR, str_repeat("\u{2591}", $rest));
        fwrite(STDERR, " " . sprintf("% 3d", (int)(100*$p)) . "%");
        fwrite(STDERR, sprintf(' %0.2dMB/%0.2dMB', $memory, $peak) . "\r");
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
            implode(DIRECTORY_SEPARATOR, [
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
