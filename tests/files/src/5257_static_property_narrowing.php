<?php

/**
 * Test that static properties are narrowed correctly with type-check functions.
 */

interface SingletonInterface
{
    /**
     * @return static
     */
    public static function getInstance(): static;

    /**
     * @param static|null $instance
     */
    public static function setInstance($instance): void;
}

class Singleton implements SingletonInterface
{
    /** @var static|null */
    private static $instance = null;

    public static function getInstance(): static
    {
        // After this check, self::$instance should be narrowed to exclude null
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        // Should NOT warn - type is narrowed to static (non-null)
        return self::$instance;
    }

    public static function setInstance($instance): void
    {
        self::$instance = $instance;
    }

    public static function resetInstance(): void
    {
        self::setInstance(null);
    }
}

class TypeNarrowingTests
{
    /** @var string|null */
    private static $stringOrNull = null;

    /** @var array|null */
    private static $arrayOrNull = null;

    /** @var object|null */
    private static $objectOrNull = null;

    /** @var mixed */
    private static $mixed;

    public static function testIsNull(): void
    {
        if (is_null(self::$stringOrNull)) {
            // In this branch, type should be null
            echo strlen(self::$stringOrNull); // Should warn - type is null
        } else {
            // In this branch, type should be string (non-null)
            echo strlen(self::$stringOrNull); // Should NOT warn
        }
    }

    public static function testNotIsNull(): void
    {
        if (!is_null(self::$stringOrNull)) {
            // In this branch, type should be string (non-null)
            echo strlen(self::$stringOrNull); // Should NOT warn
        } else {
            // In this branch, type should be null
            echo strlen(self::$stringOrNull); // Should warn - type is null
        }
    }

    public static function testIsString(): void
    {
        if (is_string(self::$mixed)) {
            // In this branch, type should be string
            echo strlen(self::$mixed); // Should NOT warn
        }
    }

    public static function testIsArray(): void
    {
        if (is_array(self::$arrayOrNull)) {
            // In this branch, type should be array (non-null)
            echo count(self::$arrayOrNull); // Should NOT warn
        }
    }

    public static function testIsObject(): void
    {
        if (is_object(self::$objectOrNull)) {
            // In this branch, type should be object (non-null)
            $hash = spl_object_hash(self::$objectOrNull); // Should NOT warn
        }
    }
}

class StaticKeywordTest
{
    /** @var static|null */
    protected static $instanceStatic = null;

    public static function getInstanceStatic(): static
    {
        if (is_null(static::$instanceStatic)) {
            static::$instanceStatic = new static();
        }

        // Should NOT warn - type is narrowed to static (non-null)
        return static::$instanceStatic;
    }
}
