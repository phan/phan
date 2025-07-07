<?php
/**
 * @param mixed $a
 * @param ?mixed $b
 * @param mixed|object $c
 * @param mixed|non-empty-mixed $d
 * @return false|mixed
 */
function testMixed($a, $b, $c, $d) {
    return $a !== 'foo' && $b !== null && $c !== '' && $d !== '' ? $GLOBALS['unknown'] : false;
}
return testMixed(1, 2, 3, 4);
