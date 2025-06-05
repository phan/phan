<?php
/**
 * @param false $a
 * @param true $b
 * @param null $c
 * @return false|true|null
 */
function testFalseTrueNullAlone($a, $b, $c) {
    return [ $a, $b, $c ][ rand( 0, 2 ) ];
}
echo testFalseTrueNullAlone(false, true, null) ? 'a' : 'b';

/**
 * @param false|string $a
 * @param true|int $b
 * @param null|float $c
 * @param true|false $bool
 * @return false|true|null
 */
function testFalseTrueNullUnion($a, $b, $c, $bool) {
    return $a !== false && $b !== true && $c !== null ? $bool : null;
}
echo testFalseTrueNullUnion(false, true, null, true) ? 'a' : 'b';
