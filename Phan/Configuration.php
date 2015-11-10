<?php declare(strict_types=1);
namespace Phan;

/**
 * Program configuration
 */
class Configuration {

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

        // Run a quick version of checks that takes less
        // time
        'quick_mode' => false,

        // The vesion of the AST (defined in php-ast)
        // we're using
        'ast_version' => 30,
    ];

    /**
     * Disallow the constructor to force a singleton
     */
    private function __construct() {}

    /**
     * @return Configuration
     * Get a Configuration singleton
     */
    public static function instance() {
        static $instance = null;

        if ($instance) {
            return $instance;
        }

        $instance = new Configuration();
        return $instance;
    }

    public function __get(string $name) {
        return $this->configuration[$name];
    }

    public function __set(string $name, $value) {
        $this->configuration[$name] = $value;
    }
}
