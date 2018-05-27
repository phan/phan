<?php
function test_if_assign() {
    if (rand() % 2 > 0 || ($x = rand() % 2) > 0) {
        throw new RuntimeException('throw');
    }
    return $x;
}
function test_unused_if_assign() {
    if (rand() % 2 > 0 || ($x = rand() % 2) > 0) {
        throw new RuntimeException('throw');
    }
}
test_if_assign();
test_unused_if_assign();
