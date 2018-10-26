<?php

/**
 * @property-read   int $read_only
 * @property-write  int $write_only
 */
class TestReadWriteOnly {
    /**
     * @var string
     * @phan-read-only
     */
    public $real_read_only = 'default';
    /**
     * @var string
     * @phan-write-only
     */
    public $real_write_only = 'default';

    public function __get(string $name) {
        return strlen($name);
    }

    public function __set(string $name, $value) {
        var_export([$name, $value]);
    }
}
call_user_func(function () {
    $c = new TestReadWriteOnly();
    $c->read_only = 2;
    var_export($c->read_only);
    $a = $c->read_only;
    var_export($a);
    $c->write_only = 2;
    var_export($c->write_only);
    $a = $c->write_only;
    var_export($a);

    $c = new TestReadWriteOnly();
    $c->real_read_only = 'x';
    var_export($c->real_read_only);
    $a = $c->real_read_only;
    var_export($a);
    $c->real_write_only = 'x';
    var_export($c->real_write_only);
    $a = $c->real_write_only;
    var_export($a);
});
