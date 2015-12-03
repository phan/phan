<?php declare(strict_types=1);
namespace Phan;

use \Phan\Config;
use \Phan\Log;

class CLI {

    /**
     * @var string[]
     * The set of file names to analyze
     */
    private $file_list = [];

    /**
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     */
    public function __construct() {
        global $argv;

        // Parse command line args
        $opts = getopt("f:m:o:c:haqbrpis:3:t::");

        foreach($opts ?? [] as $key => $value) {
            switch($key) {
            case 'h':
                $this->usage();
                break;
            case 'f':
                if(is_file($value) && is_readable($value)) {
                    $this->file_list =
                        file($value, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
                } else {
                    Log::err(Log::EFATAL, "Unable to open $value");
                }
                break;
            case 'm':
                if(!in_array($value, ['verbose','short','json','csv'])) {
                    $this->usage("Unknown output mode: $value");
                }
                Log::setOutputMode($value);
                break;
            case 'c':
                Config::get()
                    ->parent_constructor_required =
                    explode(',', $value);
                break;
            case 'q':
                Config::get()->quick_mode = true;
                break;
            case 'b':
                Config::get()->backward_compatibility_checks = true;
                break;
            case 'p':
                Config::get()->progress_bar = true;
                break;
            case 'a':
                Config::get()->dump_ast = true;
                break;
            case 'o':
                Log::setFilename($value);
                break;
            case 'i':
                Log::setOutputMask(Log::getOutputMask()^Log::EUNDEF);
                break;
            case 't':
                Config::get()->emit_trace_id = true;
                break;
            case '3':
                Config::get()->third_party_directory_list =
                    explode(',', $value);
                break;
            case 's':
                Config::get()->serialized_code_base_file = $value;
                break;
            case 'r':
                Config::get()->reanalyze_file_list = true;
                break;
            default:
                $this->usage("Unknown option '-$key'"); break;
            }
        }

        $pruneargv = array();
        foreach($opts ?? [] as $opt => $value) {
            foreach($argv as $key => $chunk) {
                $regex = '/^'. (isset($opt[1]) ? '--' : '-') . $opt . '/';

                if ($chunk == $value
                    && $argv[$key-1][0] == '-'
                    || preg_match($regex, $chunk)
                ) {
                    array_push($pruneargv, $key);
                }
            }
        }

        while($key = array_pop($pruneargv)) {
            unset($argv[$key]);
        }

        if(empty($this->file_list) && count($argv) < 2) {
            Log::err(Log::EFATAL, "No files to analyze");
        }

        foreach($argv as $arg) if($arg[0]=='-') {
            $this->usage("Unknown option '{$arg}'");
        }

        $this->file_list = array_merge(
            $this->file_list,
            array_slice($argv,1)
        );
    }

    /**
     * @return string[]
     * Get the set of files to analyze
     */
    public function getFileList() : array {
        return $this->file_list;
    }

    private function usage(string $msg='') {
        global $argv;

        if(!empty($msg)) {
            echo "$msg\n";
        }

        echo <<<EOB
Usage: {$argv[0]} [options] [files...]
  -f <filename>   A file containing a list of PHP files to be analyzed
  -q              Quick mode - doesn't recurse into all function calls
  -b              Check for potential PHP 5 -> PHP 7 BC issues
  -i              Ignore undeclared functions and classes
  -c              Comma-separated list of classes that require parent::__construct() to be called
  -m <mode>       Output mode: verbose, short, json, csv
  -o <filename>   Output filename
  -p              Show progress bar
  -t              Emit trace IDs on messages (for grouping error types)
  -s <filename>   Save state to the given file and read from it to speed up
                  future executions
  -r              Force a re-analysis of any files passed in even if they haven't
                  changed since the last analysis
  -h              This help

EOB;
        exit;
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
        $p = min(max($p, 1.0), 0.0);

        if (!Config::get()->progress_bar || Config::get()->dump_ast) {
            return;
        }

        // Don't update every time when we're moving
        // super fast
        if (rand(0, 100)
            > (100 * Config::get()->progress_bar_sample_rate)
        ) {
            return;
        }

        $memory = memory_get_usage()/1024/1024;
        $peak = memory_get_peak_usage()/1024/1024;

        $padded_message = str_pad ($msg, 10, ' ', STR_PAD_LEFT);

        echo "$padded_message ";
        $current = (int)($p * 60);
        $rest = max(60 - $current, 0);
        echo str_repeat("\u{25b1}", $current);
        echo str_repeat("\u{25b0}", $rest);
        echo " " . sprintf("% 3d", (int)(100*$p)) . "%";
        echo sprintf(' %0.2dMB/%0.2dMB', $memory, $peak);
        echo "\r";
    }

}
