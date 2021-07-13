<?php

namespace N955;

trait T1 {
    /** @var int */
    public static $property = 0;
    public static function main(): void {
        T1::$property += 1; // should warn
        self::$property += 1; // should not warn, it refers to the class using this method if the method was called through a class using this method.
    }
}
trait T2 {
    use T1;
}

class C {
    use T2;
}
T2::main();
var_dump(C::$property);
C::$property = 5;
C::main();
