<?php

namespace Phan\Command;

use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Log;
use Phan\PhanWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class AnalyzeCommand
 */
class AnalyzeCommand extends Command
{
    const DEFAULT_CONFIG = __DIR__ . '/../Resources/config/default_config.yml';

    /** @var  CodeBase */
    private $codebase;

    /**
     * AnalyzeCommand constructor.
     * @param null|string $name
     * @param CodeBase $codeBase
     * @throws \LogicException
     */
    public function __construct($name, CodeBase $codeBase)
    {
        parent::__construct($name);
        $this->codebase = $codeBase;
    }

    protected function configure()
    {
        $this
            ->setDescription('Analyzes given file|directory set')
            ->addArgument('targets', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'list of files or directories to analyze')
            ->addOption('file-set', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY)
            ->addOption('recursive-dir', 'r', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Include directory recursively from analyzing (but not from loading)')
            ->addOption('exclude', 'e', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Exclude directory recursively from analyzing (but not from loading)')
            ->addOption('progress', 'p', InputOption::VALUE_NONE)
            ->addOption('database', 'd', InputOption::VALUE_OPTIONAL, 'Database filename. Save state to the given file and read from it to speed up future executions')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL)
            ->addOption('expand-targets-dependencies', null, InputOption::VALUE_OPTIONAL, 'Database filename. Save state to the given file and read from it to speed up future executions')
            ->addOption('quick-mode', null, InputOption::VALUE_NONE, 'Quick mode')
            ->addOption('bc-scan', 'b', InputOption::VALUE_NONE, 'Perform scan for potential PHP 5 -> PHP 7 issues')
            ->addOption('dump-ast', null, InputOption::VALUE_NONE, 'Dump ast tree')
            ->addOption('ignore-undefined', null, InputOption::VALUE_NONE, 'Ignore undeclared functions and classes')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format', 'text')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output filename')
            ->addOption('reanalyze-all', null, InputOption::VALUE_OPTIONAL, 'Reanalyze any files passed in even if they haven\'t changed since the last analysis')
            ->addOption('trace', null, InputOption::VALUE_OPTIONAL, 'Emit trace IDs on messages (for grouping error types)')
            ->addOption('dead-code-detection', 'x', InputOption::VALUE_OPTIONAL, 'Database filename. Save state to the given file and read from it to speed up future executions')
            ->addOption('recursive-dir-mask', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Include directory recursively from analyzing (but not from loading)', ['*.php']);

        $this->addUsage(<<<USAGEBOUNDARY

USAGEBOUNDARY
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->setDecorated(true);

        $this->checkOptionsConflicts($input);

        $targets = $this->processTargetsOption($input);
        $configuration = $this->processConfigurationOption($input);
        $configuration = $this->processExcludesOption($input, $configuration);
        $configuration = $this->processDatabaseOption($input, $configuration);
        $configuration = $this->processQuickModeOption($input, $configuration);
        $configuration = $this->processBackwardCompatibilityCheckOption($input, $configuration);
        $configuration = $this->processFormatOptions($input, $configuration);

        if ($input->getOption('reanalyze-all')) {
            $configuration['phan']['reanalyze_file_list'] = true;
        }

        $output = $this->processOutputOption($input, $output);

        $this->doRun($targets, $configuration['phan'], $input, $output);
    }

    private function checkOptionsConflicts(InputInterface $input)
    {
        if ($input->getOption('expand-targets-dependencies') && null === $input->getOption('database')) {
            throw new \LogicException('Requesting an expanded dependency list can only be done if a state-file is defined');
        }
    }

    /**
     * @param InputInterface $input
     * @return array|mixed
     * @throws \InvalidArgumentException
     */
    protected function processTargetsOption(InputInterface $input)
    {
        $targets = $input->getArgument('targets');

        if (
            0 === count($targets) &&
            0 === count($input->getOption('file-set')) &&
            0 === count($input->getOption('recursive-dir'))
        ) {
            throw  new \InvalidArgumentException('You should specify either -f option or -r option or targets');
        }

        foreach ($input->getOption('file-set') as $filename) {
            $targets = array_merge($targets, file(
                $filename,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            ));
        }

        $finder = new Finder();
        foreach ($input->getOption('recursive-dir-mask') as $mask) {
            $finder->name($mask);
        }

        foreach ($input->getOption('recursive-dir') as $dirname) {
            foreach ($finder->files()->in($dirname) as $file) {
                /** @var $file \SplFileInfo */
                $targets[] = $file->getRealPath();
            }
        }
        return $targets;
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    private function processConfigurationOption(InputInterface $input):array
    {
        $filename = $input->getOption('config');

        $defaultConfig = Yaml::parse(file_get_contents(self::DEFAULT_CONFIG));
        $config = [];
        if (null !== $filename) {
            try {
                $config = Yaml::parse(file_get_contents($filename));
            } catch (\Exception $exception) {
                throw new \InvalidArgumentException('Invalid config file provided: ' . $exception->getMessage(), 0, $exception);
            }
        }

        $config = array_replace_recursive($defaultConfig, $config);
        return $config;
    }

    /**
     * @param InputInterface $input
     * @param array $configuration
     * @return array
     */
    protected function processExcludesOption(InputInterface $input, $configuration): array
    {
        $configuration['phan']['exclude_analysis_directory_list'] = [];
        foreach ($input->getOption('exclude') as $directory) {
            $configuration['phan']['exclude_analysis_directory_list'][] = realpath($directory);
        }
        return $configuration;
    }

    /**
     * @param InputInterface $input
     * @param array $configuration
     * @return array
     */
    private function processDatabaseOption(InputInterface $input, array $configuration): array
    {
        if (null !== $input->getOption('database')) {
            $configuration['phan']['stored_state_file_path'] = $input->getOption('database');
        }
        return $configuration;
    }

    /**
     * @param InputInterface $input
     * @param array $configuration
     * @return array
     */
    private function processQuickModeOption(InputInterface $input, array $configuration): array
    {
        $configuration['phan']['quick_mode'] = $input->getOption('quick-mode');
        return $configuration;
    }

    /**
     * @param InputInterface $input
     * @param array $configuration
     * @return array
     */
    private function processBackwardCompatibilityCheckOption(InputInterface $input, array $configuration): array
    {
        if ($input->getOption('backward-compatibility-scan')) {
            $configuration['phan']['backward_compatibility_checks'] = true;
        }

        return $configuration;
    }

    /**
     * @param InputInterface $input
     * @param array $configuration
     * @return array
     * @throws \InvalidArgumentException
     */
    private function processFormatOptions(InputInterface $input, array $configuration): array
    {
        if ($input->getOption('ignore-undefined')) {
            Log::setOutputMask(Log::getOutputMask() ^ Issue::CATEGORY_UNDEFINED);
        }

        if (!in_array($input->getOption('format'), ['text', 'codeclimate'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s', $input->getOption('format')));
        }

        Log::setOutputMode($input->getOption('format'));

        if ($input->getOption('trace')) {
            $configuration['phan']['emit_trace_id'] = true;
        }

        return $configuration;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return OutputInterface
     */
    private function processOutputOption(InputInterface $input, OutputInterface $output): OutputInterface
    {
        $outputOption = $input->getOption('output');
        if (null !== $outputOption) {
            // Todo: check output is writeable here
            $output = new StreamOutput(fopen($input->getOption('output'), 'w'));

            //Todo: rewrite to not use static output
            Log::setFilename($input->getOption('output'));
        }

        return $output;
    }

    private function doRun(array $targets, array $configuration, InputInterface $input, OutputInterface $output)
    {
        $config = Config::fromArray($configuration);

        $progressOutput = new NullOutput();
        if ($input->getOption('progress')) {
            $progressOutput = new ConsoleOutput();
        }

        $parser = new PhanWrapper($config, $output, $progressOutput);

        if ($input->getOption('expand-targets-dependencies')) {
            $targets = $parser->dependencyFileList($this->codebase, $targets);
        }

        $parser->analyzeFileList($this->codebase, $targets);
    }
}