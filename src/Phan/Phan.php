<?php declare(strict_types=1);
namespace Phan;

class Phan {

    /**
     * Analyze the given set of files and emit any issues
     * found to STDOUT.
     *
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param string[] $file_path_list
     * A list of files to scan
     *
     * @return null
     * We emit messages to STDOUT. Nothing is returned.
     *
     * @see \Phan\CodeBase
     */
    public static function analyzeFileList(CodeBase $code_base, array $file_path_list)
    {
        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $analyze_file_path_list = [];

        // This first pass parses code and populates the
        // global state we'll need for doing a second
        // analysis after.
        foreach ($file_path_list as $i => $file_path) {
            CLI::progress('parse', ($i + 1) / $file_count);

            // Kick out anything we read from the former version
            // of this file
            $code_base->flushDependenciesForFile($file_path);

            // If the file is gone, no need to continue
            if (!file_exists($file_path)) {
                continue;
            }

            try {
                // Parse the file
                Analysis::parseFile($code_base, $file_path);

                // Update the timestamp on when it was last
                // parsed
                $code_base->setParseUpToDateForFile($file_path);

                // Save this to the set of files to analyze
                $analyze_file_path_list[] = $file_path;

            } catch (\Throwable $throwable) {
                error_log($file_path . ' ' . $throwable->getMessage() . "\n");
            }
        }

        // Don't continue on to analysis if the user has
        // chosen to just dump the AST
            if (Config::get()->dump_ast) {
            exit;
        }

        // Take a pass over all classes verifying various
        // states now that we have the whole state in
        // memory
        Analysis::analyzeClasses($code_base);

        // Take a pass over all functions verifying
        // various states now that we have the whole
        // state in memory
        Analysis::analyzeFunctions($code_base);

        // We can only save classes, methods, properties and
        // constants after we've merged parent classes in.
        $code_base->store();

        // Once we know what the universe looks like we
        // can scan for more complicated issues.
        $file_count = count($analyze_file_path_list);
        foreach ($analyze_file_path_list as $i => $file_path) {
            CLI::progress('analyze', ($i + 1) / $file_count);

            // We skip anything defined as 3rd party code
            // to save a lil' time
            if (self::isExcludedAnalysisFile($file_path)) {
                continue;
            }

            // Analyze the file
            Analysis::analyzeFile($code_base, $file_path);
        }

        // Scan through all globally accessible elements
        // in the code base and emit errors for dead
        // code.
        Analysis::analyzeDeadCode($code_base);

        // Emit all log messages
        Log::display();
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
     */
    public static function expandedFileList(
        CodeBase $code_base,
        array $file_path_list
    ) : array {

        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $dependency_file_path_list = [];

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
    public static function isExcludedAnalysisFile(string $file_path) : bool
    {
        foreach (Config::get()->exclude_analysis_directory_list
                 as $directory
        ) {
            if (0 === strpos($file_path, $directory) || 0 === strpos($file_path, "./$directory")) {
                return true;
            }
        }

        return false;
    }
}
