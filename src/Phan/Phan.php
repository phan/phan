<?php

declare(strict_types=1);

namespace Phan;

use AssertionError;
use Closure;
use Exception;
use InvalidArgumentException;
use Phan\AST\TolerantASTConverter\Shim;
use Phan\Daemon\Request;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Logger as LanguageServerLogger;
use Phan\Library\FileCache;
use Phan\Library\StringUtil;
use Phan\Output\BufferedPrinterInterface;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\IgnoredFilesFilterInterface;
use Phan\Output\IssueCollectorInterface;
use Phan\Output\IssuePrinterInterface;
use Phan\Plugin\ConfigPluginSet;

use function array_combine;
use function array_filter;
use function array_flip;
use function array_merge;
use function array_values;
use function count;
use function file_exists;
use function file_put_contents;
use function fprintf;
use function fwrite;
use function gc_enable;
use function getmypid;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function preg_match;
use function realpath;
use function sort;
use function sprintf;
use function str_replace;
use function var_representation;

use const EXIT_FAILURE;
use const EXIT_SUCCESS;
use const JSON_PRETTY_PRINT;
use const PHP_DEBUG;
use const SORT_STRING;
use const STDERR;

/**
 * This executes the parse, method/function, then the analysis phases.
 *
 * This is the entry point of Phan's implementation.
 * Implementations such as `./phan` or the code climate integration call into this.
 *
 * @see self::analyzeFileList()
 */
class Phan implements IgnoredFilesFilterInterface
{
    /** @var IssuePrinterInterface used to print formatted issues. */
    public static $printer;

    /** @var IssueCollectorInterface used to gather issues to be printed (or used) once analysis is finished */
    private static $issue_collector;

    /**
     * @return IssueCollectorInterface used to gather issues to be printed (or used) once analysis is finished
     */
    public static function getIssueCollector(): IssueCollectorInterface
    {
        return self::$issue_collector;
    }

    /**
     * Set the IssueCollectorInterface used to gather issues to be printed (or used) once analysis is finished
     * @param IssueCollectorInterface $issue_collector
     */
    public static function setIssueCollector(
        IssueCollectorInterface $issue_collector
    ): void {
        self::$issue_collector = $issue_collector;
    }

    /**
     * Take an array of serialized issues, unserialize them and then add
     * them to the issue collector.
     *
     * @param list<IssueInstance> $issues
     */
    private static function collectSerializedResults(array $issues): void
    {
        $collector = self::getIssueCollector();
        foreach ($issues as $issue) {
            $collector->collectIssue($issue);
        }
    }

    /**
     * A list of classes to preload, before forking analysis workers with pcntl.
     * This was based on Phan's self-analysis with Phan's own settings.
     * Other projects may have a few more classes to preload.
     *
     * Note that interfaces and traits would require calling interface_exists() or trait_exists() instead.
     */
    private const DAEMON_PRELOAD_CLASSES = [
        \Phan\Analysis\AbstractMethodAnalyzer::class,
        \Phan\Analysis\ArgumentType::class,
        \Phan\Analysis\AssignmentVisitor::class,
        \Phan\Analysis\AssignOperatorAnalysisVisitor::class,
        \Phan\Analysis\BlockExitStatusChecker::class,
        \Phan\Analysis\ClassInheritanceAnalyzer::class,
        \Phan\Analysis\CompositionAnalyzer::class,
        \Phan\Analysis\ConditionVisitor::class,
        \Phan\Analysis\ConditionVisitor\ComparisonCondition::class,
        \Phan\Analysis\ConditionVisitor\IdenticalCondition::class,
        \Phan\Analysis\ConditionVisitor\NotIdenticalCondition::class,
        \Phan\Analysis\ContextMergeVisitor::class,
        \Phan\Analysis\DuplicateClassAnalyzer::class,
        \Phan\Analysis\DuplicateFunctionAnalyzer::class,
        \Phan\Analysis\FallbackMethodTypesVisitor::class,
        \Phan\Analysis\LoopConditionVisitor::class,
        \Phan\Analysis\NegatedConditionVisitor::class,
        \Phan\Analysis\ParameterTypesAnalyzer::class,
        \Phan\Analysis\ParentConstructorCalledAnalyzer::class,
        \Phan\Analysis\PostOrderAnalysisVisitor::class,
        \Phan\Analysis\PreOrderAnalysisVisitor::class,
        \Phan\Analysis\PropertyTypesAnalyzer::class,
        \Phan\Analysis\ReachabilityChecker::class,
        \Phan\Analysis\RedundantCondition::class,
        \Phan\Analysis\RegexAnalyzer::class,
        \Phan\Analysis\ThrowsTypesAnalyzer::class,
        \Phan\AST\ASTHasher::class,
        \Phan\AST\ASTReverter::class,
        \Phan\AST\FallbackUnionTypeVisitor::class,
        \Phan\AST\InferPureVisitor::class,
        \Phan\AST\InferValue::class,
        \Phan\AST\ScopeImpactCheckingVisitor::class,
        \Phan\BlockAnalysisVisitor::class,
        \Phan\Daemon\ParseRequest::class,
        \Phan\Daemon\Request::class,
        \Phan\Daemon\Transport\StreamResponder::class,
        \Phan\Exception\NodeException::class,
        \Phan\Language\Element\MarkupDescription::class,
        \Phan\Language\Scope\BranchScope::class,
        \Phan\Language\Type\NonEmptyAssociativeArrayType::class,
        \Phan\Language\Type\NonEmptyGenericArrayType::class,
        \Phan\Library\Hasher\Sequential::class,
        \Phan\Library\RegexKeyExtractor::class,
        \Phan\Ordering::class,
        \Phan\Output\Printer\CapturingJSONPrinter::class,
        \Phan\Output\Printer\FilteringPrinter::class,
        \Phan\Output\Printer\JSONPrinter::class,
        \Phan\Plugin\Internal\RedundantConditionLoopCheck::class,
        \Phan\Plugin\Internal\UseReturnValuePlugin\RedundantReturnVisitor::class,
        \Phan\Plugin\Internal\VariableTracker\VariableGraph::class,
        \Phan\Plugin\Internal\VariableTracker\VariableTrackerVisitor::class,
        \Phan\Plugin\Internal\VariableTracker\VariableTrackingBranchScope::class,
        \Phan\Plugin\Internal\VariableTracker\VariableTrackingLoopScope::class,
        \Phan\Plugin\Internal\VariableTracker\VariableTrackingScope::class,
        \Phan\PluginV3\StopParamAnalysisException::class,
        \Phan\Suggestion::class,
        \Symfony\Component\Console\Output\BufferedOutput::class,
    ];

    /**
     * Analyze the given set of files and emit any issues
     * found to STDOUT.
     *
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Closure():(list<string>) $file_path_lister
     * Returns a list of files to scan
     *
     * @return bool
     * We emit messages to the configured printer and return
     * true if issues were found.
     *
     * @see \Phan\CodeBase
     *
     * @throws Exception if analysis fails unrecoverably or in an unexpected way
     *
     * @suppress PhanPluginRemoveDebugAny
     */
    public static function analyzeFileList(
        CodeBase $code_base,
        Closure $file_path_lister
    ): bool {
        if (Config::getValue('dump_parsed_file_list') === true) {
            // If --dump-parsed-file-list is provided,
            // print the files in the order they would be parsed.
            $file_path_list = $file_path_lister();
            echo \implode("\n", $file_path_list) . (count($file_path_list) > 0 ? "\n" : "");
            exit(EXIT_SUCCESS);
        }
        if (Config::getValue('dump_parsed_file_list') === CLI::DUMP_ANALYZED) {
            $file_path_list = array_filter($file_path_lister(), static function (string $file_path): bool {
                return !self::isExcludedAnalysisFile($file_path);
            });
            echo \implode("\n", $file_path_list) . (count($file_path_list) > 0 ? "\n" : "");
            exit(EXIT_SUCCESS);
        }
        if (CLI::isDaemonOrLanguageServer() &&
            Config::getValue('language_server_use_pcntl_fallback')) {
            // The PCNTL fallback generates cyclic references (to the CodeBase instance which references many other things) in createRestorePoint,
            // so we need to garbage collect that.
            // This is probably the only part of the code which generates cyclic references
            //
            // 1. Phan clones the old codebase to restore it, and cyclic references exist as a side effect.
            //
            //    This causes memory usage to increase while typing.
            //
            //    Memory inspection/profiling would help with creating a better fix.
            // 2. It's possible that some plugins may benefit from garbage collection.
            //
            // This fix works in PHP 7.3, which has an improved garbage collector.
            // It might not work as well in earlier PHP versions on large codebases.
            gc_enable();
        }

        Shim::load();
        FileCache::setMaxCacheSize(FileCache::MINIMUM_CACHE_SIZE);
        self::checkForSlowPHPOptions();
        Config::warnIfInvalid();
        if (Config::getValue('processes') !== 1) {
            if (!\extension_loaded('pcntl')) {
                throw new AssertionError('The pcntl extension must be loaded in order for Phan to be able to fork.');
            }
        }
        self::loadConfiguredPHPExtensionStubs($code_base);
        $is_daemon_request = Config::getValue('daemonize_socket') || Config::getValue('daemonize_tcp');
        $language_server_config = Config::getValue('language_server_config');
        $is_undoable_request = is_array($language_server_config) || $is_daemon_request;
        if ($is_daemon_request) {
            $code_base->eagerlyLoadAllSignatures();
        }
        if ($is_undoable_request) {
            self::checkForOptionsConflictingWithServerModes();
            $code_base->enableUndoTracking();
        }

        $file_path_list = $file_path_lister();

        $file_count = count($file_path_list);
        if ($file_count === 0) {
            fprintf(STDERR, "Phan did not parse any files in the project %s - This may be an issue with the Phan config or CLI options.\n", StringUtil::jsonEncode(Config::getProjectRootDirectory()));
        }

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $analyze_file_path_list = [];

        if (Config::getValue('consistent_hashing_file_order')) {
            // Parse the files in lexicographic order.
            // If there are duplicate class/function definitions,
            // this ensures they are added to the maps in the same order.
            sort($file_path_list, SORT_STRING);
        }

        // This first pass parses code and populates the
        // global state we'll need for doing a second
        // analysis after.
        CLI::progress('parse', 0.0, null, 0, $file_count);
        $code_base->setCurrentParsedFile(null);
        foreach ($file_path_list as $i => $file_path) {
            $file_path = (string)$file_path;

            $code_base->setCurrentParsedFile($file_path);
            CLI::progress('parse', ($i + 1) / $file_count, $file_path, $i + 1, $file_count);

            // Kick out anything we read from the former version
            // of this file
            $code_base->flushDependenciesForFile($file_path);

            // If the file is gone, no need to continue
            $real = realpath($file_path);
            if ($real === false || !file_exists($real)) {
                CLI::printWarningToStderr("Could not find file '$file_path'\n");
                continue;
            }
            try {
                // Parse the file
                Analysis::parseFile($code_base, $file_path);

                // Save this to the set of files to analyze
                $analyze_file_path_list[] = $file_path;
            } catch (\AssertionError $assertion_error) {
                CLI::printErrorToStderr("While parsing $file_path...\n");
                fwrite(STDERR, "$assertion_error\n");
                exit(EXIT_FAILURE);
            } catch (\Throwable $throwable) {
                // Catch miscellaneous errors such as $throwable and print their stack traces.
                CLI::printWarningToStderr("While parsing $file_path, caught: " . $throwable . "\n");
                $code_base->recordUnparsableFile($file_path);
            }
        }
        $code_base->setCurrentParsedFile(null);
        ConfigPluginSet::instance()->beforeAnalyze($code_base);
        if ($is_undoable_request) {
            $code_base->setExpectChangesToFileContents();
        }

        // Don't continue on to analysis if the user has
        // chosen to just dump the AST
        if (Config::getValue('dump_ast')) {
            exit(EXIT_SUCCESS);
        }

        if (is_string(Config::getValue('dump_signatures_file'))) {
            exit(self::dumpSignaturesToFile($code_base, Config::getValue('dump_signatures_file')));
        }

        $temporary_file_mapping = [];

        $request = null;
        Type::clearAllMemoizations();
        if ($is_undoable_request) {
            if (!$code_base->isUndoTrackingEnabled()) {
                throw new AssertionError("Expected undo tracking to be enabled");
            }
            if ($is_daemon_request) {
                // Preload classes before forking daemon or language server worker processes with pcntl.
                //
                // Technically, this is only useful if pcntl is installed,
                // but always doing this is easier to reason about.
                if (is_array($language_server_config)) {
                    throw new AssertionError('cannot use language server config for daemon mode');
                }
                self::preloadBeforeForkingAnalysisWorkers($code_base);
                // Garbage collecting cycles doesn't help or hurt much here. Thought it would change something..
                // TODO: check for conflicts with other config options -
                //    incompatible with dump_ast, dump_signatures_file, output-file, etc.
                //    incompatible with dead_code_detection

                // This will fork and fall through every time a request to re-analyze the file set comes in.
                // TODO: The daemon should be periodically restarted?
                $request = Daemon::run($code_base, $file_path_lister);
                if (!$request) {
                    // TODO: Add a way to cleanly shut down.
                    fwrite(STDERR, "Finished serving requests, exiting\n");
                    exit(2);
                }
            } else {
                if (!is_array($language_server_config)) {
                    throw new AssertionError("Language server config must be an array");
                }
                LanguageServerLogger::logInfo(sprintf("Starting accepting connections on the language server (pid=%s)", getmypid() ?: 'unknown'));
                $request = LanguageServer::run($code_base, $file_path_lister, $language_server_config);
                if (!$request) {
                    // TODO: Add a way to cleanly shut down.
                    fwrite(STDERR, "Finished serving requests, exiting\n");
                    exit(2);
                }
                LanguageServerLogger::logInfo(sprintf("language server (pid=%s) accepted connection", getmypid() ?: 'unknown'));
            }
            self::setPrinter($request->getPrinter());

            // This is the list of all of the parsed files
            // (Also includes files which don't declare classes/functions/constants)
            $analyze_file_path_list = $request->filterFilesToAnalyze($code_base->getParsedFilePathList());
            if (count($analyze_file_path_list) === 0) {
                $request->respondWithNoFilesToAnalyze();  // respond and exit.
                exit(0);  // This is normal (E.g. .txt files, files outside of analysis list, etc)
            }

            // Do this before we stop tracking undo operations.
            $temporary_file_mapping = $request->getTemporaryFileMapping();

            // Stop tracking undo operations, now that the parse phase is done.
            $code_base->disableUndoTracking();
        }

        return self::finishAnalyzingRemainingStatements($code_base, $request, $analyze_file_path_list, $temporary_file_mapping);
    }

    private static function preloadBeforeForkingAnalysisWorkers(CodeBase $code_base): void
    {
        if (Config::getValue('language_server_use_pcntl_fallback')) {
            return;
        }
        // Preloading classes takes around 8 milliseconds
        foreach (self::DAEMON_PRELOAD_CLASSES as $preload_class) {
            if (!\class_exists($preload_class)) {
                throw new AssertionError("Failed to preload $preload_class before starting daemon mode");
            }
        }
        // ensureScopeInitialized takes around 40 milliseconds for Phan self-analysis
        $preload_function_state = static function (FunctionInterface $function_or_method) use (
            $code_base
        ): void {
            if ($function_or_method->isPHPInternal()) {
                return;
            }
            // Phan always has to call this, to add default values to types of parameters.
            // This is slow, which is why this is done before calling pcntl_fork(), not after.
            // If pcntl was unavailable, ensureScopeInitialized would not be undone
            // See https://github.com/phan/phan/issues/3771
            $function_or_method->ensureScopeInitialized($code_base);
        };
        $function_map = $code_base->getFunctionMap();
        foreach ($function_map as $function) {  // iterate, ignoring $fqsen
            $preload_function_state($function);
        }
        $method_set = $code_base->getMethodSet();
        foreach ($method_set as $method) {  // iterate, ignoring $fqsen
            $preload_function_state($method);
        }
        // Calling getMethodsMapGroupedByDefiningFQSEN takes around 11 milliseconds for Phan self-analysis
        // Preload this - it's high latency to generate the map in a forked analysis worker process.
        $code_base->getMethodsMapGroupedByDefiningFQSEN();
    }

    private static function checkForOptionsConflictingWithServerModes(): void
    {
        if (Config::isIssueFixingPluginEnabled()) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            fwrite(STDERR, "Cannot use --automatic-fix in daemon mode or with the language server\n");
            exit(EXIT_FAILURE);
        }
    }

    /**
     * Finish analyzing any files that need to be analyzed.
     * (for full analysis, or a limited number of files for daemon mode, etc.)
     *
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param ?Request $request
     * @param list<string> $analyze_file_path_list
     * @param array<string,string> $temporary_file_mapping
     *
     * @throws Exception if analysis failed catastrophically
     */
    public static function finishAnalyzingRemainingStatements(
        CodeBase $code_base,
        ?Request $request,
        array $analyze_file_path_list,
        array $temporary_file_mapping
    ): bool {
        try {
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

            // This is only needed when `pcntl` *isn't* used.
            // This object (if non-null) removes the temporary plugins added to implement "Go To Definition", etc.
            $raii = ConfigPluginSet::instance()->addTemporaryAnalysisPlugin($code_base, $request);

            // TODO: consider filtering if Config::getValue('include_analysis_file_list') is set
            // most of what needs considering is that Analysis::analyzeClasses() and Analysis:analyzeFunctions() have side effects
            // these side effects don't matter in daemon mode, but they do matter with this other form of incremental analysis
            // other parts of these analysis steps could be skipped, which would reduce the overall execution time
            $path_filter = isset($request) ? array_flip($analyze_file_path_list) : null;

            // Tie class aliases together with the source class
            if (Config::getValue('enable_class_alias_support')) {
                $code_base->resolveClassAliases();
            }

            // Take a pass over all classes verifying
            // various states now that we have the whole
            // state in memory
            Analysis::analyzeClasses($code_base, $path_filter);

            // Take a pass over all functions verifying
            // various states now that we have the whole
            // state in memory
            Analysis::analyzeFunctions($code_base, $path_filter);

            if (Config::getValue('dump_matching_functions')) {
                exit(EXIT_SUCCESS);
            }

            Analysis::loadMethodPlugins($code_base);

            ConfigPluginSet::instance()->beforeAnalyzePhase($code_base);

            // Filter out any files that are to be excluded from
            // analysis
            $analyze_file_path_list = array_values(array_filter(
                $analyze_file_path_list,
                static function (string $file_path): bool {
                    return !self::isExcludedAnalysisFile($file_path);
                }
            ));
            if ($request instanceof Request && count($analyze_file_path_list) === 0) {
                $request->respondWithNoFilesToAnalyze();
                exit(0);
            }

            // Get the count of all files we're going to analyze
            $file_count = count($analyze_file_path_list);

            // Prevent an ugly failure if we have no files to
            // analyze.
            if (0 === $file_count) {
                return false;
            }

            // Get a map from process_id to the set of files that
            // the given process should analyze in a stable order
            $process_file_list_map =
                (new Ordering($code_base))->orderForProcessCount(
                    Config::getValue('processes'),
                    $analyze_file_path_list
                );

            /**
             * This worker takes a file and analyzes it
             */
            $analysis_worker = static function (int $i, string $file_path, int $file_count) use ($code_base, $temporary_file_mapping, $request): void {
                CLI::progress('analyze', ($i + 1) / $file_count, $file_path, $i + 1, $file_count);
                Analysis::analyzeFile($code_base, $file_path, $request, $temporary_file_mapping[$file_path] ?? null);
            };

            // Determine how many processes we're running on. This may be
            // less than the provided number if the files are bunched up
            // excessively.
            $process_count = count($process_file_list_map);

            if (!($process_count > 0 && $process_count <= Config::getValue('processes'))) {
                throw new AssertionError(
                    "The process count must be between 1 and the given number of processes. After mapping files to cores, $process_count process were set to be used."
                );
            }

            $did_fork_pool_have_error = false;

            CLI::progress('analyze', 0.0, null, 0, $file_count);
            $analyze_twice = Config::getValue('__analyze_twice');
            // Check to see if we're running as multiple processes
            // or not
            if ($process_count > 1) {
                if ($analyze_twice) {
                    CLI::printWarningToStderr("cannot run analysis phase twice when using --processes N\n");
                }
                // Run analysis one file at a time, splitting the set of
                // files up among a given number of child processes.
                $pool = new ForkPool(
                    $process_file_list_map,
                    static function (): void {
                        // Remove any issues that were collected prior to forking
                        // to prevent duplicate issues in the output.
                        self::getIssueCollector()->reset();
                    },
                    $analysis_worker,
                    /**
                     * @return list<IssueInstance> the list of collected issues from calls to collectIssue()
                     */
                    static function () use ($code_base): array {
                        // This closure is run once, after running analysis_worker on each input.
                        // If there are any plugins defining finalizeProcess(), run those.
                        ConfigPluginSet::instance()->finalizeProcess($code_base);

                        // Return the collected issues to be serialized.
                        return self::getIssueCollector()->getCollectedIssues();
                    }
                );

                // Wait for all tasks to complete and collect the results.
                self::collectSerializedResults($pool->wait());
                $did_fork_pool_have_error = $pool->didHaveError();
            } else {
                // Get the task data from the 0th processor
                $analyze_file_path_list = array_values($process_file_list_map)[0];

                // If we're not running as multiple processes, just iterate
                // over the file list and analyze them
                foreach ($analyze_file_path_list as $i => $file_path) {
                    $analysis_worker($i, $file_path, $file_count);
                }

                if ($analyze_twice) {
                    CLI::resetLongProgressState();
                    CLI::progress('analyze', 0.0, null, 0, $file_count);
                    foreach ($analyze_file_path_list as $i => $file_path) {
                        $analysis_worker($i, $file_path, $file_count);
                    }
                }

                // Scan through all globally accessible elements
                // in the code base and emit errors for dead
                // code.
                Analysis::analyzeDeadCode($code_base);

                // If there are any plugins defining finalizeProcess(), run those.
                ConfigPluginSet::instance()->finalizeProcess($code_base);
            }

            // Get a count of the number of issues that were found
            $issue_count = count((self::$issue_collector)->getCollectedIssues());
            $is_issue_found =
                0 !== $issue_count;

            // Indicate that --progress-bar or --debug has finished, if needed.
            CLI::endProgressBar();

            // Collect all issues, blocking
            self::display();

            if (Config::getValue('print_memory_usage_summary')) {
                self::printMemoryUsageSummary();
            }
        } catch (Exception $e) {
            if ($request instanceof Request) {
                // Give people using the language server client/daemon a somewhat useful response.
                $request->sendJSONResponse([
                    "status" => Request::STATUS_ERROR_UNKNOWN,
                    "issue_count" => 1,
                    "issues" => 'Failed to analyze files: Uncaught exception: ' . (string)$e,
                ]);
                $request->exit(EXIT_SUCCESS);
            }
            throw $e;
        }

        if ($request instanceof Request) {
            $request->respondWithIssues($issue_count);
            $request->exit(EXIT_SUCCESS);
        }

        if ($did_fork_pool_have_error) {
            // Make fork pool errors (e.g. due to memory limits) easy to detect when running CI jobs.
            return true;
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
     * @return list<string>
     * Get an expanded list of files and dependencies for
     * the given file list
     *
     * TODO: This is no longer referenced, was removed while sqlite3 was temporarily removed.
     *       It would help in daemon mode if this was re-enabled
     * @suppress PhanUnreferencedPublicMethod potentially useful but currently unused
     */
    public static function expandedFileList(
        CodeBase $code_base,
        array $file_path_list
    ): array {

        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $dependency_file_path_list = [];

        CLI::progress('dependencies', 0.0, null);  // trigger UI update of 0%
        foreach ($file_path_list as $i => $file_path) {
            CLI::progress('dependencies', ($i + 1) / $file_count, $file_path);

            // Add the file itself to the list
            $dependency_file_path_list[] = $file_path;

            // Add any files that depend on this file
            $dependency_file_path_list = array_merge(
                $dependency_file_path_list,
                $code_base->dependencyListForFile($file_path)
            );
        }

        return array_values(array_combine($dependency_file_path_list, $dependency_file_path_list));
    }

    /**
     * @return bool
     * True if this file is a member of a third party directory as
     * configured via the CLI flag '-3 [paths]'.
     */
    public static function isExcludedAnalysisFile(
        string $file_path
    ): bool {
        $include_analysis_file_list = Config::getValue('include_analysis_file_list');
        if ($include_analysis_file_list) {
            return !in_array($file_path, $include_analysis_file_list, true);
        }

        $file_path = str_replace('\\', '/', $file_path);
        $exclude_analysis_regex = Config::getValue('__exclude_analysis_regex');
        if ($exclude_analysis_regex) {
            if (preg_match($exclude_analysis_regex, $file_path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Emit all collected issues
     */
    private static function display(): void
    {
        $collector = self::$issue_collector;

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
    private static function dumpSignaturesToFile(CodeBase $code_base, string $filename): int
    {
        $encoded_signatures = json_encode($code_base->exportFunctionAndMethodSet(), JSON_PRETTY_PRINT);
        if (!file_put_contents($filename, $encoded_signatures)) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            fprintf(STDERR, "Could not save contents to path '%s'\n", $filename);
            return EXIT_FAILURE;
        }
        return EXIT_SUCCESS;
    }

    private static function printMemoryUsageSummary(): void
    {
        $memory = memory_get_usage() / 1024 / 1024;
        $peak   = memory_get_peak_usage() / 1024 / 1024;
        // @phan-suppress-next-line PhanPluginRemoveDebugCall
        fwrite(STDERR, sprintf("Memory usage after analysis completed: %.02dMB/%.02dMB\n", $memory, $peak));
    }

    /**
     * Set the printer to use for emitting issues.
     */
    public static function setPrinter(
        IssuePrinterInterface $printer
    ): void {
        self::$printer = $printer;
    }

    /**
     * @param string $filename
     *
     * @return bool True if filename is ignored during analysis
     */
    public function isFilenameIgnored(string $filename): bool
    {
        return self::isExcludedAnalysisFile($filename);
    }

    /**
     * Logs slow php options to stdout
     */
    private static function checkForSlowPHPOptions(): void
    {
        static $did_check = false;
        if ($did_check) {
            // Only perform this check once (e.g. in unit tests
            return;
        }
        $did_check = true;
        if (Config::getValue('skip_slow_php_options_warning')) {
            return;
        }
        $warned = false;
        // Unless debugging Phan itself, these two configurations are unnecessarily adding slowness.
        if (PHP_DEBUG) {
            CLI::printHelpSection("WARNING: Phan is around twice as slow when php is compiled with --enable-debug (That option is only needed when debugging Phan itself).\n", false, true);
            $warned = true;
        }
        // We warn about Xdebug in src/codebase.php, so skip that check here.
        if ($warned) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            fwrite(STDERR, "(The above warning(s) about slow PHP settings can be disabled by setting 'skip_slow_php_options_warning' to true in .phan/config.php)\n");
        }
    }

    /**
     * Loads configured stubs for internal PHP extensions.
     * @throws InvalidArgumentException if the stubs or stub config is invalid
     */
    private static function loadConfiguredPHPExtensionStubs(CodeBase $code_base): void
    {
        $stubs = Config::getValue('autoload_internal_extension_signatures');
        foreach ($stubs ?: [] as $extension_name => $path_to_extension) {
            $extension_name = (string)$extension_name;
            // Prefer using reflection info from the running extension over what's in the stub files.
            // (The originals were already added to the CodeBase)
            if (\extension_loaded($extension_name)) {
                continue;
            }
            if (!is_string($path_to_extension)) {
                throw new \InvalidArgumentException("Invalid autoload_internal_extension_signatures: path for $extension_name is not a string: value: " . var_representation($path_to_extension));
            }
            $path_to_extension = Config::projectPath($path_to_extension);
            if (!is_file($path_to_extension)) {
                throw new \InvalidArgumentException("Invalid autoload_internal_extension_signatures: path for $extension_name is not a file: value: " . var_representation($path_to_extension));
            }
            Analysis::parseFile($code_base, $path_to_extension, false, null, true);
        }
    }
}
