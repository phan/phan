<?php

/**
 * Test case for GitHub issue #5423
 * Static property conditions should not produce false positives
 *
 * @see https://github.com/phan/phan/issues/5423
 */

class Test5423 {
    public static stdClass $obj;
    public static ?stdClass $nullableObj;
    public static string $str;
    public static int $num;

    public static function checkObject(): void {
        // Should NOT warn - static properties can be uninitialized or change between invocations
        if (self::$obj) {
            echo "Has object\n";
        }
    }

    public static function checkNullable(): void {
        // Should NOT warn - nullable is expected to be checked
        if (self::$nullableObj) {
            echo "Has nullable object\n";
        }
    }

    public static function checkNegated(): void {
        // Should NOT warn - negated condition on static property
        if (!self::$obj) {
            echo "No object\n";
        }
    }

    public static function checkString(): void {
        // Should NOT warn - string can be empty
        if (self::$str) {
            echo "Has string\n";
        }
    }

    public static function checkNum(): void {
        // Should NOT warn - int can be 0
        if (self::$num) {
            echo "Has number\n";
        }
    }
}

class Test5423Static {
    public static stdClass $obj;

    public static function check(): void {
        // Should NOT warn - using 'static' keyword
        if (static::$obj) {
            echo "Has object\n";
        }
    }
}

class Test5423Parent extends Test5423 {
    public static function check(): void {
        // Should NOT warn - using 'parent' keyword
        if (parent::$obj) {
            echo "Has object\n";
        }
    }
}
