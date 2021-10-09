<?php

class X206 {
    private static $prop;
    /** @var int */
    private static $prop2;
    /** @var int */
    private static $prop3;
    public static function set($value) {
        return self::$prop ??= $value;
    }

    public static function callCount() {
        return ++self::$prop2;
    }
    public static function writeUnused() {
        // prop3 isn't used elsewhere
        ++self::$prop3;
    }
}
var_dump(X206::set(123));
var_dump(X206::callCount());
var_dump(X206::writeUnused());
