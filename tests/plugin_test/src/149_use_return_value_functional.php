<?php
/**
 * @param int[] $values
 * @return int[]
 */
function odds149(array $values) : array {
    return array_filter($values, function (int $x) {
        return $x % 2 != 0;
    });
}
var_export(odds149([2,3,4,5]));
odds149([-1, 0, 1]);
