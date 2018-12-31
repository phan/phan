<?php

/**
 * @template T1
 * @template T3
 * @param Closure(T1):mixed $f1
 * @param Closure(mixed):T3 $f2
 * @return Closure(T1):T3 $f3
 */
function combine_closures(Closure $f1, Closure $f2) : Closure
{
    /**
     * @param T1 $arg
     * @return T3
     */
    return function ($arg) use ($f1, $f2) {
        return $f2($f1($arg));
    };
}

$combination = combine_closures(
    /** @param string[] $strings */
    function (array $strings) : string {
        return implode(',', $strings);
    },
    function (string $value) : stdClass {
        return (object)['value' => $value];
    }
);
var_export($combination('key'));

/**
 * @param array<int,string> $values
 * @return string
 */
function my_implode(string $joiner, array $values) : string {
    return implode($joiner, $values);
}

/**
 * @template T4
 * @template T5
 * @param callable(mixed, T4):T5 $f1
 * @param mixed $first
 * @return Closure(T4):T5 $f3
 */
function bind_first_argument(Closure $f1, $first) : Closure
{
    /**
     * @param T4 $arg
     * @return T5
     */
    return function ($arg) use ($f1, $first) {
        return $f1($first, $arg);
    };
}

$join_with_commas = bind_first_argument('my_implode', ',');
var_export($join_with_commas([new stdClass()]));
