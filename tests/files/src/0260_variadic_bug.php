<?php

class VariadicBug {
    /**
     * @param string $a
     * @param int[]|string[] $arguments
     */
    public static function nonvaradic($a, $arguments) {
        printf("%s\n", json_encode($arguments));
    }

    /**
     * @param string $a
     * @param int|string ...$arguments
     */
    public static function foo(string $a, ...$arguments) {
        if (count($arguments) > 0) {
            // self::nonvariadic($a, $arguments);
        }
        if (count($arguments) > 0) {
            self::nonvaradic($a, $arguments);
        }
        self::nonvaradic($a, $arguments);
    }
}

/**
 * @param string $a
 * @param int|string ...$arguments
 */
function variadic_bug_260(string $a, ...$arguments) {
    if (count($arguments) > 0) {
        // self::nonvariadic($a, $arguments);
    }
    if (count($arguments) > 0) {
        VariadicBug::nonvaradic($a, $arguments);
    }
    VariadicBug::nonvaradic($a, $arguments);
}

VariadicBug::foo('label', 'a', 2);
VariadicBug::foo('label', 'a', [2]);
variadic_bug_260('label', 'a', 2);
variadic_bug_260('label', 'a', [2]);
