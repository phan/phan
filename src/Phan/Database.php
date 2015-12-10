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
        assert(!is_dir(Config::get()->stored_state_file_path),
            "State file '{Config::get()->stored_state_file_path}' cannot be a directory"
        );

        assert(!file_exists(Config::get()->stored_state_file_path)
            || (
                is_writable(Config::get()->stored_state_file_path)
                && is_readable(Config::get()->stored_state_file_path)
            ),
            "State file '{Config::get()->stored_state_file_path}' must be readable and writable"
        );

        parent::__construct(
            Config::get()->stored_state_file_path,
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
                throw new \Exception("Database not enabled");
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
        return (bool)Config::get()->stored_state_file_path;
    }

}
