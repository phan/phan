<?php

class StaticPropSetterExample {
    private static $prop;

    public static function setProperty(stdClass $val): void {
        self::$prop = $val;
    }

    public static function getProperty(): stdClass {
        if (self::$prop === null) {
            self::setProperty(new stdClass());
        }
        return self::$prop;
    }
}
