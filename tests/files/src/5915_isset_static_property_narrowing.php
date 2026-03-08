<?php

// Test 1: isset(self::$prop) should narrow static property to non-null
// (tests ConditionVisitor::checkComplexIsset)
class IssetNarrow5915 {
    /** @var string|null */
    private static $value = null;

    public static function getValue(): ?string {
        if (isset(self::$value)) {
            $v = self::$value;
            '@phan-debug-var $v';
            return $v;
        }
        return null;
    }
}

// Test 2: !isset(self::$prop) should narrow to null before any assignment
// (tests NegatedConditionVisitor::checkComplexIsset)
class NotIssetNarrow5915 {
    /** @var int|string|null */
    private static $data = null;

    public static function check(): void {
        if (!isset(self::$data)) {
            // Before any assignment, self::$data should be narrowed to null
            $d = self::$data;
            '@phan-debug-var $d';
        }
    }
}

// Test 3: !isset + assignment pattern should preserve phpdoc types through merge
class IssetAssign5915 {
    /** @var int|string|null */
    private static $data = null;

    public static function getData(): int|string {
        if (!isset(self::$data)) {
            self::$data = 42;
        }
        $d = self::$data;
        '@phan-debug-var $d';
        return $d;
    }
}
