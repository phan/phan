<?php

class C762 {
    protected $prop1;
    protected static $prop2;
    public static function main() {
        return $prop1;
    }
    public static function main2() {
        return $prop2;
    }
    public static function main3() {
        return self::$prop3;
    }
}
