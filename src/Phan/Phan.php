<?php declare(strict_types=1);
namespace Phan;

use Phan\Daemon\Request;
use Phan\Output\BufferedPrinterInterface;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\IgnoredFilesFilterInterface;
use Phan\Output\IssueCollectorInterface;
use Phan\Output\IssuePrinterInterface;

class Phan implements IgnoredFilesFilterInterface {

    /** @var IssuePrinterInterface */
    public static $printer;

    /** @var IssueCollectorInterface */
    private static $issueCollector;

    /**
     * @return IssueCollectorInterface
     */
    public static function getIssueCollector() : IssueCollectorInterface {
        return self::$issueCollector;
    }

    /**
     * @param IssueCollectorInterface $issueCollector
     *
     * @return void
     */
    public static function setIssueCollector(
        IssueCollectorInterface $issueCollector
    ) {
        self::$issueCollector = $issueCollector;
    }

    /**
     * Take an array of serialized issues, deserialize them and then add
     * them to the issue collector.
     *
     * @param array $results
     */
    private static function collectSerializedResults(array $results)
    {
        $collector = self::getIssueCollector();
        foreach ($results as $issues) {
            if (empty($issues)) {
                continue;
            }

            foreach ($issues as $issue) {
                $collector->collectIssue($issue);
            }
        }
    }

    /**
     * Analyze the given set of files and emit any issues
     * found to STDOUT.
     *
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param \Closure string[] $file_path_lister
     * Returns a list of files to scan (string[])
     *
     * @return bool
     * We emit messages to the configured printer and return
     * true if issues were found.
     *
     * @see \Phan\CodeBase
     */
    public static function analyzeFileList(
        CodeBase $code_base,
        \Closure $file_path_lister
    ) : bool {
        $is_daemon_request = Config::get()->daemonize_socket || Config::get()->daemonize_tcp_port;
        if ($is_daemon_request) {
            $code_base->enableUndoTracking();
        }

        $file_path_list = $file_path_lister();

        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $analyze_file_path_list = [];

        if (Config::get()->consistent_hashing_file_order) {
            // Parse the files in lexicographic order.
            // If there are duplicate class/function definitions,
            // this ensures they are added to the maps in the same order.
            sort($file_path_list, SORT_STRING);
        }

        if (Config::get()->dump_parsed_file_list === true) {
            // If --dump-parsed-file-list is provided,
            // print the files in the order they would be parsed.
            echo implode("\n", $file_path_list) . (count($file_path_list) > 0 ? "\n" : "");
            exit(EXIT_SUCCESS);
        }

        // This first pass parses code and populates the
        // global state we'll need for doing a second
        // analysis after.
        CLI::progress('parse', 0.0);
        $code_base->setCurrentParsedFile(null);
        foreach ($file_path_list as $i => $file_path) {
            $code_base->setCurrentParsedFile($file_path);
            CLI::progress('parse', ($i + 1) / $file_count);

            // Kick out anything we read from the former version
            // of this file
            $code_base->flushDependenciesForFile($file_path);

            // If the file is gone, no need to continue
            if (($real = realpath($file_path)) === false || !file_exists($real)) {
                continue;
            }
            try {
                // Parse the file
                Analysis::parseFile($code_base, $file_path);

                // Save this to the set of files to analyze
                $analyze_file_path_list[] = $file_path;


            } catch (\AssertionError $assertion_error) {
                error_log("While parsing $file_path...\n");
                error_log("$assertion_error\n");
                exit(EXIT_FAILURE);
            } catch (\Throwable $throwable) {
                error_log($file_path . ' ' . $throwable->getMessage() . "\n");
                $code_base->recordUnparseableFile($file_path);
            }
        }
        $code_base->setCurrentParsedFile(null);

        // Don't continue on to analysis if the user has
        // chosen to just dump the AST
        if (Config::get()->dump_ast) {
            exit(EXIT_SUCCESS);
        }

        if (is_string(Config::get()->dump_signatures_file)) {
            exit(self::dumpSignaturesToFile($code_base, Config::get()->dump_signatures_file));
        }

        $temporary_file_mapping = [];

        $request = null;
        if ($is_daemon_request) {
            assert($code_base->isUndoTrackingEnabled());
            // Garbage collecting cycles doesn't help or hurt much here. Thought it would change something..
            // TODO: check for conflicts with other config options - incompatible with dump_ast, dump_signatures_file, output-file, etc.
            // incompatible with dead_code_detection
            $request = Daemon::run($code_base, $file_path_lister);  // This will fork and fall through every time a request to re-analyze the file set comes in. The daemon should be periodically restarted?
            if (!$request) {
                // TODO: Add a way to cleanly shut down.
                error_log("Finished serving requests, exiting");
                exit(2);
            }
            assert($request instanceof Request);
            self::$printer = $request->getPrinter();

            // This is the list of all of the parsed files
            // (Also includes files which don't declare classes/functions/constants)
            $analyze_file_path_list = $request->filterFilesToAnalyze($code_base->getParsedFilePathList());
            if (count($analyze_file_path_list) === 0)  {
                $request->respondWithNoFilesToAnalyze();  // respond and exit.
            }
            // Do this before we stop tracking undo operations.
            $temporary_file_mapping = $request->getTemporaryFileMapping();

            // Stop tracking undo operations, now that the parse phase is done.
            $code_base->disableUndoTracking();
        }

        global $start_time;
        $start_time = microtime(true);

        // With parsing complete, we need to tell the code base to
        // start hydrating any requested elements on their way out.
        // Hydration expands class types, imports parent methods,
        // properties, etc., and does stuff like that.
        //
        // This is an optimization that saves us a significant
        // amount of time on very large code bases. Instead of
        // hydrating all classes, we only hydrate the things we
        // actually need. When running as multiple processes this
        // lets us only need to do hydrate a subset of classes.
        $code_base->setShouldHydrateRequestedElements(true);


        $path_filter = isset($request) ? array_flip($analyze_file_path_list) : null;
        // Take a pass over all functions verifying
        // various states now that we have the whole
        // state in memory
        Analysis::analyzeClasses($code_base, $path_filter);

        // Take a pass over all functions verifying
        // various states now that we have the whole
        // state in memory
        Analysis::analyzeFunctions($code_base, $path_filter);

        // Filter out any files that are to be excluded from
        // analysis
        $analyze_file_path_list = array_filter(
            $analyze_file_path_list,
            function($file_path) {
                return !self::isExcludedAnalysisFile($file_path);
            }
        );
        if ($request instanceof Request && count($analyze_file_path_list) === 0)  {
            $request->respondWithNoFilesToAnalyze();
            exit(0);
        }

        // Get the count of all files we're going to analyze
        $file_count = count($analyze_file_path_list);

        // Prevent an ugly failure if we have no files to
        // analyze.
        if (0 == $file_count) {
            return false;
        }

        // Get a map from process_id to the set of files that
        // the given process should analyze in a stable order
        $process_file_list_map =
            (new Ordering($code_base))->orderForProcessCount(
                Config::get()->processes,
                $analyze_file_path_list
            );

        // This worker takes a file and analyzes it
        $analysis_worker = function($i, $file_path)
            use ($file_count, $code_base, $temporary_file_mapping) {
                CLI::progress('analyze', ($i + 1) / $file_count);
                Analysis::analyzeFile($code_base, $file_path, $temporary_file_mapping[$file_path] ?? null);
            };

        // Determine how many processes we're running on. This may be
        // less than the provided number if the files are bunched up
        // excessively.
        $process_count = count($process_file_list_map);

        assert($process_count > 0 && $process_count <= Config::get()->processes,
            "The process count must be between 1 and the given number of processes. After mapping files to cores, $process_count process were set to be used.");

        CLI::progress('analyze', 0.0);
        // Check to see if we're running as multiple processes
        // or not
        if ($process_count > 1) {

            // Run analysis one file at a time, splitting the set of
            // files up among a given number of child processes.
            $pool = new ForkPool(
                $process_file_list_map,
                function () {
                    // Remove any issues that were collected prior to forking
                    // to prevent duplicate issues in the output.
                    self::getIssueCollector()->reset();
                },
                $analysis_worker,
                function () {
                    // Return the collected issues to be serialized.
                    return self::getIssueCollector()->getCollectedIssues();
                }
            );

            // Wait for all tasks to complete and collect the results.
            self::collectSerializedResults($pool->wait());

        } else {
            // Get the task data from the 0th processor
            $analyze_file_path_list = array_values($process_file_list_map)[0];

            // If we're not running as multiple processes, just iterate
            // over the file list and analyze them
            foreach ($analyze_file_path_list as $i => $file_path) {
                $analysis_worker($i, $file_path);
            }

            // Scan through all globally accessible elements
            // in the code base and emit errors for dead
            // code.
            Analysis::analyzeDeadCode($code_base);
        }

        // Get a count of the number of issues that were found
        $issue_count = count((self::$issueCollector)->getCollectedIssues());
        $is_issue_found =
            0 !== $issue_count;

        // Collect all issues, blocking
        self::display();
        if ($request instanceof Request) {
            $request->respondWithIssues($issue_count);
            exit(0);
        }

        return $is_issue_found;
    }

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param string[] $file_path_list
     * A set of files to expand with the set of dependencies
     * on those files.
     *
     * @return string[]
     * Get an expanded list of files and dependencies for
     * the given file list
     *
     * TODO: This is no longer referenced, was removed while sqlite3 was temporarily removed.
     *       It would help in daemon mode if this was re-enabled
     */
    public static function expandedFileList(
        CodeBase $code_base,
        array $file_path_list
    ) : array {

        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $dependency_file_path_list = [];

        CLI::progress('dependencies', 0.0);  // trigger UI update of 0%
        foreach ($file_path_list as $i => $file_path) {
            CLI::progress('dependencies', ($i + 1) / $file_count);

            // Add the file itself to the list
            $dependency_file_path_list[] = $file_path;

            // Add any files that depend on this file
            $dependency_file_path_list = array_merge(
                $dependency_file_path_list,
                $code_base->dependencyListForFile($file_path)
            );
        }

        return array_unique($dependency_file_path_list);
    }

    /**
     * @return bool
     * True if this file is a member of a third party directory as
     * configured via the CLI flag '-3 [paths]'.
     */
    private static function isExcludedAnalysisFile(
        string $file_path
    ) : bool {
        // TODO: add an alternative of a whitelist of files.
        // Incompatible with exclude_analysis_directory_list
        $file_path = str_replace('\\', '/', $file_path);
        foreach (Config::get()->exclude_analysis_directory_list
                 as $directory
        ) {
            if (0 === strpos($file_path, $directory)
                || 0 === strpos($file_path, "./$directory")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Emit all collected issues
     *
     * @return void
     */
    private static function display() {
        $collector = self::$issueCollector;

        $printer = self::$printer;

        foreach ($collector->getCollectedIssues() as $issue) {
            $printer->print($issue);
        }

        if ($collector instanceof BufferingCollector) {
            $collector->flush();
        }

        if ($printer instanceof BufferedPrinterInterface) {
            $printer->flush();
        }
    }

    /**
     * Save json encoded function&method signature to a map.
     * @return int - Exit code for process
     */
    private static function dumpSignaturesToFile(CodeBase $code_base, string $filename) : int {
        $encoded_signatures = json_encode($code_base->exportFunctionAndMethodSet(), JSON_PRETTY_PRINT);
        if (!file_put_contents($filename, $encoded_signatures)) {
            error_log(sprintf("Could not save contents to path '%s'\n", $filename));
            return EXIT_FAILURE;
        }
        return EXIT_SUCCESS;
    }

    /**
     * @return void
     */
    public static function setPrinter(
        IssuePrinterInterface $printer
    ) {
        self::$printer = $printer;
    }

    /**
     * @param string $filename
     *
     * @return bool True if filename is ignored during analysis
     */
    public function isFilenameIgnored(string $filename):bool {
        return self::isExcludedAnalysisFile($filename);
    }
}
