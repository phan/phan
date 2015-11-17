<?php declare(strict_types=1);
namespace Phan;

/**
 * Program configuration
 */
class Config {

    /**
     * Configuration options
     */
    private $configuration = [
        // Backwards Compatibility Checking
        'backward_compatibility_checks' => true,

        // A set of fully qualified class-names for which
        // a call to parent::__construct() is required
        'parent_constructor_required' => [],

        // Include a progress bar in the output
        'progress_bar' => false,

        // The probability of actually emitting any
        // progress bar update
        'progress_bar_sample_rate' => 0.1,

        // Run a quick version of checks that takes less
        // time
        'quick_mode' => false,

        // The vesion of the AST (defined in php-ast)
        // we're using
        'ast_version' => 30,

        // Set to true in order to prepend all emitted error
        // messages with an ID indicating the distinct class
        // of issue seen. This allows us to get counts of
        // distinct error types.
        'emit_trace_id' => false,

        // A list of directories holding 3rd party code that
        // we only want to parse, but not analyze
        'third_party_directory_list' => [],

        // If true, missing properties will be created when
        // they are first seen. If false, we'll report an
        // error message.
        'allow_missing_properties' => true,
    ];

    /**
     * Disallow the constructor to force a singleton
     */
    private function __construct() {}

    /**
     * @return Configuration
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
}
