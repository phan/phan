<?php

// Test 1: Basic self::$prop assignment after null check
class Container5283 {
    private static $instance = null;

    public static function getInstance(): Container5283 {
        if (self::$instance === null) {
            self::$instance = new Container5283();
        }
        '@phan-debug-var self::$instance';
        return self::$instance;  // Should NOT error - type should be Container5283
    }
}

// Test 2: Using static:: keyword
class Registry5283 {
    private static $data = null;

    public static function getData(): Registry5283 {
        if (static::$data === null) {
            static::$data = new static();
        }
        '@phan-debug-var static::$data';
        return static::$data;  // Should NOT error
    }
}

// Test 3: Using parent:: keyword
class Base5283 {
    protected static $config = null;
}

class Derived5283 extends Base5283 {
    public static function getConfig(): Base5283 {
        if (parent::$config === null) {
            parent::$config = new Base5283();
        }
        '@phan-debug-var parent::$config';
        return parent::$config;  // Should NOT error
    }
}

// Test 4: Assignment to variable after static property update
class Cache5283 {
    private static $cache = null;

    public static function getCache(): Cache5283 {
        if (self::$cache === null) {
            self::$cache = new Cache5283();
        }
        $result = self::$cache;
        '@phan-debug-var $result';
        return $result;  // Should NOT error
    }
}

// Test 5: Multiple assignments and nested conditionals
class Complex5283 {
    private static $primary = null;
    private static $secondary = null;

    public static function getPrimary(): Complex5283 {
        if (self::$primary === null) {
            self::$primary = new Complex5283();
            if (self::$secondary === null) {
                self::$secondary = new Complex5283();
            }
        }
        '@phan-debug-var self::$primary';
        '@phan-debug-var self::$secondary';
        return self::$primary;  // Should NOT error
    }

    public static function getSecondary(): ?Complex5283 {
        return self::$secondary;  // May be null
    }
}

// Test 6: Verify that non-self/static/parent still works normally
class External5283 {
    public static $external = null;
}

class User5283 {
    public static function useExternal(): ?External5283 {
        if (External5283::$external === null) {
            External5283::$external = new External5283();
        }
        return External5283::$external;  // This should still work (not self/static/parent)
    }
}
