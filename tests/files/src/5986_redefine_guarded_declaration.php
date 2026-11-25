<?php

// A polyfill does not redefine the internal function
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('helper5986') && PHP_VERSION_ID > 80000) :
    function helper5986(): int {
        return 1;
    }
endif;

// Declarations in different branches are still reported: Phan analyzes the code with only one of them
if (extension_loaded('mbstring')) {
    function scrub5986(string $s): string {
        return mb_scrub($s);
    }
    class Impl5986 {
        public function value(): int {
            return 1;
        }
    }
} else {
    function scrub5986(string $s): string {
        return $s;
    }
    class Impl5986 {
        public function value(): int {
            return 2;
        }
    }
}

if (function_exists('other5986')) {
    echo "other5986 exists\n";
} else {
    function other5986(): void {
    }
}

if (!class_exists('Guarded5986')) {
    class Guarded5986 {
    }
}

var_dump((new Impl5986())->value(), scrub5986('x'), helper5986(), str_contains('a', 'b'));
other5986();

// A polyfill that takes a different number of parameters is still reported: calls are only checked against one declaration
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack): bool {
        return $haystack !== '';
    }
}

// Without a guard, these are still reported
function strlen($s) {
    return 0;
}
function helper5986(): int {
    return 2;
}
function twice5986(): void {
}
if (PHP_VERSION_ID > 80000) {
    function twice5986(): void {
    }
    class Twice5986 {
    }
}
class Twice5986 {
}
class Guarded5986 {
}
var_dump(new Twice5986(), new Guarded5986());
