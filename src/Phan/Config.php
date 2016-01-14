<?php declare(strict_types=1);
namespace Phan;

use \Phan\Issue;

/**
 * Program configuration.
 * See `./phan -h` for command line usage, or take a
 * look at \Phan\CLI.php for more details on CLI usage.
 */
class Config {

    /**
     * @var string
     * The root directory of the project. This is used to
     * store canonical path names and find project resources
     */
    private $project_root_directory = null;

    /**
     * Configuration options
     */
    private $configuration = [

        // A list of directories holding code that we want
        // to parse, but not analyze. Directories holding
        // third party code should be set here.
        'exclude_analysis_directory_list' => [],

        // Backwards Compatibility Checking. This is slow
        // and expensive, but you should consider running
        // it before upgrading your version of PHP to a
        // new version that has backward compatibility
        // breaks.
        'backward_compatibility_checks' => true,

        // A set of fully qualified class-names for which
        // a call to parent::__construct() is required.
        'parent_constructor_required' => [],

        // Run a quick version of checks that takes less
        // time at the cost of not running as thorough
        // an analysis. You should consider setting this
        // to true only when you wish you had more issues
        // to fix in your code base.
        'quick_mode' => false,

        // The minimum severity level to report on. This can be
        // set to Issue::SEVERITY_LOW, Issue::SEVERITY_NORMAL or
        // Issue::SEVERITY_CRITICAL. Setting it to only
        // critical issues is a good place to start on a big
        // sloppy mature code base.
        'minimum_severity' => 0,

        // If true, missing properties will be created when
        // they are first seen. If false, we'll report an
        // error message if there is an attempt to write
        // to a class property that wasn't explicitly
        // defined.
        'allow_missing_properties' => false,

        // Allow null to be cast as any type and for any
        // type to be cast to null. Setting this to false
        // will cut down on false positives.
        'null_casts_as_any_type' => false,

        // Set to true in order to attempt to detect dead
        // (unreferenced) code. Keep in mind that the
        // results will only be a guess given that classes,
        // properties, constants and methods can be referenced
        // as variables (like `$class->$property` or
        // `$class->$method()`) in ways that we're unable
        // to make sense of.
        'dead_code_detection' => false,

        // If a file path is given, the code base will be
        // read from and written to the given location in
        // order to attempt to save some work from being
        // done. Only changed files will get analyzed if
        // the file is read
        'stored_state_file_path' => null,

        // Set to true in order to force a re-analysis of
        // any file passed in via the CLI even if our
        // internal state is up-to-date
        'reanalyze_file_list' => false,

        // If set to true, we'll dump the AST instead of
        // analyzing files
        'dump_ast' => false,

        // If true, we'll dump the set of dependencies
        // on the given file list instead of doing any
        // kind of analysis. This is useful for determining
        // the full set of files that should be analyzed
        // when running against a state file
        'expanded_dependency_list' => false,

        // Include a progress bar in the output
        'progress_bar' => false,

        // The probability of actually emitting any progress
        // bar update. Setting this to something very low
        // is good for reducing network IO and filling up
        // your terminal's buffer when running phan on a
        // remote host.
        'progress_bar_sample_rate' => 0.005,

        // The vesion of the AST (defined in php-ast)
        // we're using
        'ast_version' => 30,

        // Set to true to emit profiling data on how long various
        // parts of Phan took to run. You likely don't care to do
        // this.
        'profiler_enabled' => false,
    ];

    /**
     * Disallow the constructor to force a singleton
     */
    private function __construct() {}

    /**
     * @return string
     * Get the root directory of the project that we're
     * scanning
     */
    public function getProjectRootDirectory() : string {
        return $this->project_root_directory ?? getcwd();
    }

    /**
     * @param string $project_root_directory
     * Set the root directory of the project that we're
     * scanning
     *
     * @return void
     */
    public function setProjectRootDirectory(
        string $project_root_directory
    ) {
        $this->project_root_directory = $project_root_directory;
    }

    /**
     * @return Config
     * Get a Configuration singleton
     */
    public static function get() : Config {
        static $instance;

        if ($instance) {
            return $instance;
        }

        $instance = new Config();
        return $instance;
    }

    public function __get(string $name) {
        return $this->configuration[$name];
    }

    public function __set(string $name, $value) {
        $this->configuration[$name] = $value;
    }

    /**
     * @return string
     * The relative path appended to the project root directory.
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function projectPath(string $relative_path) {
        return implode(DIRECTORY_SEPARATOR, [
            Config::get()->getProjectRootDirectory(),
            $relative_path
        ]);
    }
}
