<?php

/**
 * @property-read int $read_only
 * @property-write int $write_only
 */
class PropertyChecks {
    /**
     * @var int
     * @phan-read-only
     */
    public $real_read_only = 2;
    /**
     * @var int
     * @phan-write-only
     */
    public $real_write_only = 2;

    /**
     * @var int
     * @phan-read-only
     */
    public static $real_static_read_only = 2;
    /**
     * @var int
     * @phan-write-only
     */
    public static $real_static_write_only = 2;

    public function __construct(int $x) {
        $this->real_write_only = $x;
        $this->real_read_only = 5;  // NOTE: This is permitted, but only in __construct
        $this->read_only = 3;  // This should warn - It's from a PHPDoc property-read and probably doesn't need to be initialized
        $this->write_only = 55;
        var_export($this->read_only);
        var_export($this->write_only);  // This should warn - It's write-only
    }

    public function __get(string $name) {
        return strlen($name);
    }
    public function __set(string $name, $value) {
        var_export([$name, $value]);
    }
}

PropertyChecks::$real_static_read_only = 2;  // This should warn
echo PropertyChecks::$real_static_read_only;
PropertyChecks::$real_static_write_only = 2;
echo PropertyChecks::$real_static_write_only;  // This should warn
