<?php

// These method names deliberately have the wrong case, to verify that the detection is case sensitive.
class MyClass {
    // Wrong, return value is ignored
    public function __CONSTRUCT() {
        return false;
    }

    // Fine
    public function __Call($x, $args) {
        return false;
    }

    // Wrong, return value is ignored
    public function __CLONE() {
        return false;
    }

    // Fine
    public static function __callstatic($x, $args) {
        return false;
    }

    // Fine
    public function __debugINFO() {
        return [];
    }

    // Wrong, return value is ignored
    public function __Destruct() {
        return false;
    }

    // Fine
    public function __Get($key) {
        return false;
    }

    // Fine
    public function __INVOKE($args) {
        return false;
    }

    // Fine
    public function __ISSET($key) {
        return false;
    }

    // Wrong, return value is ignored
    public function __Set($key, $value) {
        return false;
    }

    // Fine
    public function __set_State($key) {
        return new self();
    }

    // Fine
    public function __sleep($key) {
        return [];
    }

    // Fine
    public function __tostring() {
        return 'x';
    }

    // Fine
    public function __UnSet($key) {
        return false;
    }

    // Wrong, return value is ignored
    // (Throw to indicate failure)
    public function __Wakeup() {
        return false;
    }
}
