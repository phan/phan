<?php

function testUnionType(int|string $a, object|false|null $b) : int|string {
    if ($a) {
        return $a;
    }
    return $b;
}
echo testUnionType(1, null);
echo testUnionType('valid', new stdClass());
echo testUnionType('valid', false);
echo testUnionType(null, true);
echo testUnionType(new stdClass(), []);
