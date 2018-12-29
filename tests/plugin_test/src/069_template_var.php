<?php

/**
 * @template T
 * @var T $x
 * @param T $y
 * @return T
 */
function test_var_creation($y) {
    // Some unanalyzable code that creates $x
    $a = substr('xtra', 0, 1);
    $$a = $y;  // TODO: Should detect the usage of $a
    echo strlen($x);  // should warn about misusing T

    return [$x];  // should warn about returning [T] instead of T
}
test_var_creation(new stdClass());

/**
 * @template TInner
 * @param TInner $y
 * @return array<string,TInner>
 */
function test_inline_var_creation($y) {
    // Some unanalyzable code that creates $x
    $a = substr('ytra', 0, 1);
    $x = $$a;
    echo strlen($x);  // fails to infer the type of $x
    '@phan-var TInner $x';
    echo strlen($x);  // should warn about misusing TInner

    return [$x];  // should warn about returning [T] instead of T
}
test_inline_var_creation(new stdClass());

class ClassWithTemplateMethod {
    /**
     * @template TInner
     * @template TOther
     * @param TInner $y
     * @param TOther $z
     * @return TInner
     */
    public static function test_inline_var_creation($y, $z) {
        // Some unanalyzable code that creates $x
        $a = substr('ytra', 0, 1);
        $x = $$a;
        echo strlen($x);  // fails to infer the type of $x
        '@phan-var TInner $x';
        echo strlen($x);  // should warn about misusing TInner
        /**
         * @param TInner $arg
         */
        $cb = function ($arg) {
            var_export($arg);
        };
        $cb($y);
        $cb($z); // TODO: closures should be able to warn about incompatible template types
        if (rand() % 2 > 0) {
            return $z;  // should warn about returning a different template name
        }

        return [$x];  // should warn about returning [T] instead of T
    }
}
ClassWithTemplateMethod::test_inline_var_creation(new stdClass(), new ArrayObject());
