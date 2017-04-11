<?php

class A263{
    /**
     * @param array $x
     */
    public static function expect_array($x) {
    }

    /**
     * @param object $x
     */
    public static function expect_object($x) {
    }

    /**
     * @param bool $x
     */
    public static function expect_bool($x) {
    }

    /**
     * @param null $x - No code would actually be like this, just enforcing the types
     */
    public static function foo($x = null) {
        if (isset($x)) {
            self::expect_object($x);  // should not emit an issue, since Phan has no idea what $x is.
        }
    }

    /**
     * @param object|null $x
     */
    public static function with_nullable_object($x) {
        self::expect_array($x);  // type is object|null.
        if ($x) {
            self::expect_array($x);  // type is object. This cuts down on false negatives for scalar_cast and null_casts_as_any_type
            self::expect_object($x);
        }
        self::expect_array($x);  // type is object|null.
        if (!is_null($x)) {
            self::expect_array($x);  // type is object
        }
        self::expect_array($x);  // type is object|null.
        if (isset($x)) {
            self::expect_array($x);  // type is object
        }
    }

    /**
     * @param bool|null $x
     */
    public static function with_nullable_scalar($x) {
        self::expect_array($x);  // type is bool|null.
        if ($x) {
            self::expect_array($x);  // type is bool. This cuts down on false negatives for null_casts_as_any_type
            self::expect_bool($x);  // type is bool
        }
        self::expect_array($x);  // type is bool|null
        if (!is_null($x)) {
            self::expect_array($x);  // type is bool
        }
        self::expect_array($x);  // type is bool|null.
        if (isset($x)) {
            self::expect_array($x);  // type is bool
        }
    }

    /**
     * @param ?bool $x
     */
    public static function with_new_nullable_scalar($x) {
        self::expect_array($x);  // type is ?bool
        if ($x) {
            self::expect_array($x);  // type is bool. This cuts down on false negatives for null_casts_as_any_type
        }
    }
}

A263::foo();
