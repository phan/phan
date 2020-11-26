<?php
/** @return list */
function test_int_key(int $i, array $values): array {
    $z = [...$values, $i];
    '@phan-debug-var $z';
    return $z;
}
