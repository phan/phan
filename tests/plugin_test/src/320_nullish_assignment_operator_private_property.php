<?php

class TestNullCoalescingAssignmentClass
{
    private static $nullCoalesceAssignmentProperty = [];

    public static function setProperty($key, $value): mixed
    {
        return self::$nullCoalesceAssignmentProperty[$key] ??= $value;
    }
}

TestNullCoalescingAssignmentClass::setProperty('a', 'b');
