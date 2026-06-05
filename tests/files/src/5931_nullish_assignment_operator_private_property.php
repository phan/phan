<?php

class TestClass
{
    private static $property = [];

    public static function setProperty($key, $value)
    {
        return self::$property[$key] ??= $value;
    }
}

TestClass::setProperty('a', 'b');
