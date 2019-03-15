<?php

/**
 * @phan-template T
 * @param T $x
 * @throws T
 * @return T
 */
function maybe_throw($x) {
    if (rand() % 2 > 0) {
        return $x;
    }
    throw $x;
}

/**
 * Phan should do something reasonable here
 */
function test_throws_template() {
    $result = maybe_throw(new Exception("fail"));
    echo count($result);
}
test_throws_template();
