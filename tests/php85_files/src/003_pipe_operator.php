<?php

// Basic pipe operator - type inference
$result1 = "hello" |> strtoupper(...);
echo $result1; // Should be string

$result2 = [1, 2, 3] |> array_sum(...);
echo $result2; // Should be int|float

// Chained pipes - type flows through
$result3 = "test"
    |> strtoupper(...)  // string -> string
    |> str_split(...);  // string -> array

expectArray($result3);  // Should pass
expectString($result3); // Should fail - PhanTypeMismatchArgument

// Pipe with methods
class Example {
    /** @return int */
    public function double(int $x): int {
        return $x * 2;
    }

    /** @return string */
    public function toString(int $x): string {
        return (string)$x;
    }
}

$obj = new Example();
$result4 = 5 |> $obj->double(...);
expectInt($result4);     // Should pass
expectString($result4);  // Should fail - PhanTypeMismatchArgument

// Pipe with static methods
class Math {
    /** @return int */
    public static function square(int $x): int {
        return $x * $x;
    }
}

$result5 = 5 |> Math::square(...);
expectInt($result5);     // Should pass
expectString($result5);  // Should fail - PhanTypeMismatchArgument

// Chained method calls
$result6 = 10
    |> $obj->double(...)   // int -> int (20)
    |> $obj->toString(...); // int -> string ("20")

expectString($result6);  // Should pass
expectInt($result6);     // Should fail - PhanTypeMismatchArgument

// Test helper functions
/**
 * @param list<string> $x
 * @suppress PhanEmptyFunction
 * @suppress PhanPluginUseReturnValueNoopVoid
 * @suppress PhanUnusedGlobalFunctionParameter
 */
function expectArray(array $x): void {}

/**
 * @suppress PhanEmptyFunction
 * @suppress PhanPluginUseReturnValueNoopVoid
 * @suppress PhanUnusedGlobalFunctionParameter
 */
function expectString(string $x): void {}

/**
 * @suppress PhanEmptyFunction
 * @suppress PhanPluginUseReturnValueNoopVoid
 * @suppress PhanUnusedGlobalFunctionParameter
 */
function expectInt(int $x): void {}
