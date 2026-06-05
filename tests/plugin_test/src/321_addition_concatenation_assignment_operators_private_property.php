<?php

class TestAdditionConcatenationAssignmentClass
{
    private static $additionConcatenationAssignmentProperty = [];

    public static function setPropertyConcatenation($key, $value): string
    {
        return self::$additionConcatenationAssignmentProperty[$key] .= $value;
    }

    public static function setPropertyAddition($key, $value): mixed
    {
        return self::$additionConcatenationAssignmentProperty[$key] += $value;
    }
}

TestAdditionConcatenationAssignmentClass::setPropertyConcatenation('a', 'b');
TestAdditionConcatenationAssignmentClass::setPropertyAddition('a', 'b');
