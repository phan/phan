<?php

// Test 1: !empty(self::$prop) should narrow static property to non-falsey
class NotEmptyNarrow5916 {
    /** @var string|null */
    private static $name = null;

    public static function getName(): ?string {
        if (!empty(self::$name)) {
            $n = self::$name;
            '@phan-debug-var $n';
            return $n;
        }
        return null;
    }
}

// Test 2: empty(self::$prop) should narrow static property to falsey types
class EmptyNarrow5916 {
    /** @var array<string>|null */
    private static $items = null;

    public static function hasItems(): bool {
        if (empty(self::$items)) {
            $i = self::$items;
            '@phan-debug-var $i';
            return false;
        }
        return true;
    }
}
