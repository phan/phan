<?php

namespace NS794;
use stdClass;

/**
 * @param list $list
 */
function accepts_list(array $list) {
    var_export($list);
}

/**
 * @param associative-array<int,string> $a
 * @param associative-array<stdClass> $b
 */
function test_cannot_pass_associative_to_list($a, $b) {
    if (rand() % 2 === 0) {
        test_assoc($a, $b);
    }
    var_export([$a, $b]);
    accepts_list($b);
    accepts_list($a);
    call_user_func_array('var_dump', $b);
    call_user_func_array('var_dump', $a);
    // Should warn
    var_dump(...$a);
    // For php 7.4, this should not warn, this renumbers the keys
    // return [...$a];
}
/**
 * @param list<string> $a
 * @param list<stdClass> $b
 * @param non-empty-associative-array<stdClass> $c
 */
function test_cannot_past_list_to_associative(array $a, array $b, array $c, int $i) {
    test_cannot_pass_associative_to_list($a, $b);
    test_cannot_pass_associative_to_list($b, $c);
    // Should not warn
    test_cannot_pass_associative_to_list([$i => 'good'], [$i + 1 => new stdClass()]);
    test_cannot_pass_associative_to_list([], []);
    accepts_list([]);
}
