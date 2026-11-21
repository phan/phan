<?php

// A static property with the real type null stays null after a call of a method that assigns it.
class StaticNullPropertySetter {
    private static null $prop = null;

    public static function getProp(): stdClass {
        self::$prop = null;
        self::reset();
        return self::$prop;  // Should warn
    }

    private static function reset(): void {
        self::$prop = null;
    }
}
var_dump(StaticNullPropertySetter::getProp());
