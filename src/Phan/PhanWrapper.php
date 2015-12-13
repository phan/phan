<?php
/**
 * Created by PhpStorm.
 * User: Pavel
 * Date: 2015-12-14
 * Time: 21:27
 */

namespace Phan;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class PhanWrapper
{
    /** @var  LoggerInterface */
    private $logger;
    /** @var OutputInterface */
    private $progressOutput;
    /** @var  OutputInterface */
    private $messageOutput;

    /** @var Config */
    private $config;

    /**
     * Phan constructor.
     * @param Config $config
     * @param OutputInterface $progressOutput
     * @param OutputInterface $messageOutput
     */
    public function __construct(
        Config $config,
        OutputInterface $messageOutput,
        OutputInterface $progressOutput
    )
    {
        $this->config = $config;
        $this->messageOutput = $messageOutput;
        $this->logger = new ConsoleLogger($messageOutput);
        $this->progressOutput = $progressOutput;
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
     * @param string[] $file_path_list
     * A list of files to scan
     *
     * @return null
     * We emit messages to STDOUT. Nothing is returned.
     *
     * @see \Phan\CodeBase
     */
    public function analyzeFileList(
        CodeBase $code_base,
        array $file_path_list
    )
    {
        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $analyze_file_path_list = [];

        // This first pass parses code and populates the
        // global state we'll need for doing a second
        // analysis after.
        $bar = new ProgressBar($this->progressOutput);
        $bar->setFormat('%stage% %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->setBarWidth(30);
        $bar->setRedrawFrequency(5);
        $bar->start($file_count);
        $bar->setMessage('Parsing', 'stage');

        $phan = new Phan($bar);

        foreach ($file_path_list as $i => $file_path) {
            // Check to see if we need to re-parse this file either
            // because we don't have an up to date parse for this
            // file or because we were isntructed to reanalyze
            // everything
            if (!$code_base->isParseUpToDateForFile($file_path)
                || Config::get()->reanalyze_file_list) {

                // Kick out anything we read from the former version
                // of this file
                $code_base->flushDependenciesForFile(
                    $file_path
                );

                // If the file is gone, no need to continue
                if (!file_exists($file_path)) {
                    continue;
                }

                // Parse the file
                $phan->parseFile($code_base, $file_path);

                // Update the timestamp on when it was last
                // parsed
                $code_base->setParseUpToDateForFile($file_path);
            }

            $bar->advance();
        }

        $bar->finish();

        // Don't continue on to analysis if the user has
        // chosen to just dump the AST
        if ($this->config->dump_ast) {
            return;
        }

        // Take a pass over all classes verifying various
        // states now that we have the whole state in
        // memory
        $phan->analyzeClasses($code_base);

        // Take a pass over all functions verifying
        // various states now that we have the whole
        // state in memory
        $phan->analyzeFunctions($code_base);

        // We can only save classes, methods, properties and
        // constants after we've merged parent classes in.
        $code_base->store();

        // Once we know what the universe looks like we
        // can scan for more complicated issues.
        $file_count = count($analyze_file_path_list);

        $bar->start($file_count);
        $bar->setMessage('Parsing', 'stage');

        foreach ($analyze_file_path_list as $i => $file_path) {
            $bar->advance();

            // We skip anything defined as 3rd party code
            // to save a lil' time
            if (Phan::isExcludedAnalysisFile($file_path)) {
                continue;
            }

            // Analyze the file
            $phan->analyzeFile($code_base, $file_path);
        }

        $bar->finish();
        $bar->clear();

        // Scan through all globally accessible elements
        // in the code base and emit errors for dead
        // code.
        $phan->analyzeDeadCode($code_base);

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
     * Get the set of dependencies for a given file list
     */
    public function dependencyFileList(
        CodeBase $code_base,
        array $file_path_list
    ) : array {
        $file_count = count($file_path_list);

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $dependency_file_path_list = [];

        $bar = new ProgressBar($this->progressOutput);
        $bar->setFormat('%stage% %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->setBarWidth(30);
        $bar->setRedrawFrequency(5);
        $bar->start($file_count);
        $bar->setMessage('Dependencies', 'stage');

        foreach ($file_path_list as $i => $file_path) {

            $dependency_file_path_list[] = $file_path;

            $bar->advance();

            $dependency_file_path_list = array_merge(
                $dependency_file_path_list,
                $code_base->dependencyListForFile($file_path)
            );
        }

        $bar->finish();
        $bar->clear();

        return array_unique($dependency_file_path_list);
    }
}