<?php

/**
 * @param list<stdClass> $x
 * @param non-empty-list<stdClass|bool> $y
 * @return list<stdClass>
 */
function test692(array $x, array $y) {
    echo spl_object_id($x);
    echo spl_object_id($y);
    foreach ($x as $value) {
        echo $value[0];
    }
    echo $y[-1];
    return $x;
}
test692([], []);
test692([new stdClass()], [new stdClass(), false]);
test692([false], [0]);

/**
 * @return array{1:stdClass}
 */
function test_cast(stdClass ...$args) {
    '@phan-debug-var $args';
    return $args;
}
