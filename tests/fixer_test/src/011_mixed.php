<?php
/**
 * @param mixed $a
 * @param ?mixed $b
 * @param mixed|object $c
 * @return false|mixed
 */
function testMixed($a, $b, $c) {
    return $a !== 'foo' && $b !== null && $c !== '' ? $GLOBALS['unknown'] : false;
}
return testMixed(1, 2, 3);
