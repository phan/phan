<?php
/**
 * @param string|array $a
 * @param string|int|float|bool $b
 * @param ?stdClass|SplObjectStorage $c
 * @return int|string
 */
function testUnionTypes(array|string $a, bool|float|int|string $b, \SplObjectStorage|\stdClass|null $c) : int|string {
    return $a !== '' && $b !== true && $c !== false ? 42 : 'ans';
}
return testUnionTypes('a', 1, null);
