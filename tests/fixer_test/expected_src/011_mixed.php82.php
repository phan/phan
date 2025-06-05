<?php
/**
 * @param mixed $a
 * @param ?mixed $b
 * @param mixed|object $c
 * @return false|mixed
 */
function testMixed(mixed $a, ?mixed $b, mixed|object $c) : false|mixed {
    return $a !== 'foo' && $b !== null && $c !== '' ? $GLOBALS['unknown'] : false;
}
return testMixed(1, 2, 3);
