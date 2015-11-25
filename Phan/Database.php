<?php declare(strict_types=1);
namespace Phan;

use \Phan\CodeBase;
use \Phan\Config;
use \SQLite3;

/**
 * This class is the entry point into the static analyzer.
 */
class Database extends SQLite3 {
    use \Phan\Memoize;

    public function __construct() {
        parent::__construct(
            Config::get()->serialized_code_base_file,
            SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
        );

        $this->exec("PRAGMA synchronous = OFF");
        $this->exec("PRAGMA journal_mode = OFF");
        $this->exec("PRAGMA page_size = 4096");
    }

    public function __destruct() {
        $this->close();
    }

    /**
     * Get an open database to write to and read from
     */
    public static function get() : Database {
        static $instance = null;

        if (!$instance) {
            // If no database is configured, don't return
            // one
            if (!static::isEnabled()) {
                return null;
            }

            $instance = new Database();
        }

        return $instance;
    }

    /**
     * @return bool
     * True if the database is enabled
     */
    public static function isEnabled() : bool {
        return (bool)Config::get()->serialized_code_base_file;
    }

}
