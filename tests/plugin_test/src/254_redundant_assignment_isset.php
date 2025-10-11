<?php

class PossiblyUnsetConsideredNull
{
    const LIST = ['a' => '1', 'b' => '2'];

    static public function testUnset($key)
    {
        if (array_key_exists($key, self::LIST)) {
            $var = "SET " . self::LIST[$key];
        }
        if (!isset($var)) {
            $var = null;  // Should NOT warn - $var might be undefined
        }
        return $var;
    }

    static public function testUnsetNoNullSetter($key)
    {
        if (array_key_exists($key, self::LIST)) {
            $var = "SET " . self::LIST[$key];
        }
        return $var;  // Should warn - $var is possibly undeclared
    }

    static public function testActuallyRedundant($key)
    {
        $var = null;
        if (!isset($var)) {
            $var = null;  // Should warn - $var is already null
        }
        return $var;
    }

    static public function testIssetAfterAssignment($key)
    {
        if (array_key_exists($key, self::LIST)) {
            $var = null;  // Set to null
        }
        if (!isset($var)) {
            $var = null;  // Should NOT warn - $var might be undefined (not set)
        }
        return $var;
    }

    static public function testMultipleConditions($key, $key2)
    {
        if (array_key_exists($key, self::LIST)) {
            $var = "SET";
        }
        if (array_key_exists($key2, self::LIST)) {
            $var2 = "SET2";
        }
        if (!isset($var)) {
            $var = null;  // Should NOT warn - $var might be undefined
        }
        if (!isset($var2)) {
            $var2 = null;  // Should NOT warn - $var2 might be undefined
        }
        return [$var, $var2];
    }
}
