<?php

/**
 * @return int[]
 */
function test_ints(string $req, int ...$params) {
    return $params;
}
function test_vararg_within_function(DateTime ...$date_time_list) : array {
    return array_map(function (DateTime $date_time) : int {
        return 42;
    }, $date_time_list);
}

function accept_array(array $a) {
}
function accept_int(int $a) {
}

function test_misc(...$args) {
    return $args;
}


test_ints('foo',2,3,4,5);
test_ints('foo',2,3,4,[5]);
test_ints('bar');
test_ints(['baz']);
test_ints();
test_ints('baz', new DateTime());
test_ints('abc', [2,3,4,5]);

test_vararg_within_function(new DateTime());
test_vararg_within_function();
test_vararg_within_function(42);

accept_array(test_misc(2, []));  // Correct, should infer type was array.
accept_int(test_misc(2, []));  // Wrong (better error message for variadic)
// NOTE: Phan generally stores union types it infers in non-quick mode to use elsewhere.
// The addition of unrelated array types to this simple function is a consequence of that.
accept_int(test_misc(2, $argv));
accept_int(test_misc(rand(0,1) > 0, $argv));  // Should warn
accept_int(test_misc());  // Should warn
test_ints_phpdoc(3);

/**
 * @param int ...$args
 * @return int[]
 */
function test_ints_phpdoc(...$args) {
    return $args;
}
