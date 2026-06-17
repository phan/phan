<?php

class TestNestedAssignmentClass
{
    private static $nullCoalesceAssignmentProperty = [];

    private static $additionAssignmentProperty = [];

    private static $concatenationAssignmentProperty = [];

    public static function setNestedPropertyKeysWithAssignmentOperators($key, $value): Generator
    {
        yield self::$nullCoalesceAssignmentProperty[$key] ??= $value;
        yield self::$concatenationAssignmentProperty[$key] .= $value;
        yield self::$additionAssignmentProperty[$key] += $value;
    }

    public static function iterateGenerator(): array
    {
        return [...TestNestedAssignmentClass::setNestedPropertyKeysWithAssignmentOperators('a', 'b')];
    }
}

TestNestedAssignmentClass::iterateGenerator();
