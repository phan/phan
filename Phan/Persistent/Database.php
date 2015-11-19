<?php declare(strict_types=1);
namespace Phan\Persistent;

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
            $instance = new Database();
        }

        return $instance;
    }
}
