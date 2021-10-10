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
    public static function make3(...$args) {
        if ($args) {
            return new self(...$args);  // infers there's at least one argument, warns
        } else {
            return new self(...$args);  // infers there's exactly 0 arguments, doesn't warn
        }
    }
}
