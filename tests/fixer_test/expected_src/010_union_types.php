<?php
/**
 * @param string|array $a
 * @param string|int|float|bool $b
 * @param ?stdClass|SplObjectStorage $c
 * @return int|string
 */
function testUnionTypes($a, $b, $c) {
    return $a !== '' && $b !== true && $c !== false ? 42 : 'ans';
}
return testUnionTypes('a', 1, null);
