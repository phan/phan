<?php
declare(strict_types=1);
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
        'bc_checks' => true,
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

    public function __get(string $name) : bool {
        return $this->configuration[$name];
    }

    public function __set(string $name, bool $value) {
        $this->configuration[$name] = $value;
    }
}
