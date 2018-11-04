<?php

class Example {
    public function __construct() {
    }
    public static function make(...$args) {
        return new self(...$args);
    }
    public static function make2(...$args) {
        return new self('extra', ...$args);
    }
}
