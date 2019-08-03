<?php

class TypedProperties15 {
    public static int $my_int = 3;
    public static int $my_int2;
    /** @var mixed */
    public static array $my_array;
    /** @var array<int,string> */
    public static array $my_array2;
    /** @var callable-string */
    public static string $my_string;

    public static function setUp() {
        self::$my_array = [];
        self::$my_string = 'strlen';
    }

    public static function main() {
        $value = (int)self::$my_int;
        if (is_int(self::$my_int2)) {  // should warn
            echo "Definitely an int\n";
        }
        if (is_string(self::$my_int)) {  // should warn
            echo "Impossible\n";
        }
        assert(is_array(self::$my_array));  // should warn
        assert(is_array(self::$my_array2));  // should warn
        assert(is_string(self::$my_string)); // should warn - php would throw when reading if it was not a string
        if (is_callable(self::$my_string)) {  // should not warn
            echo "This is callable\n";
        }
        return $value;
    }
}
