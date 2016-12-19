<?php
// Warn about undefined constants. They are undefined even if they resemble the class name.
class A237 {
    public static function f() {
        return self;  // not the same as self::class();
    }
    public static function b() {
        return A237;  // will emit a php notice for undefined, and assume 'A237'
    }
}
