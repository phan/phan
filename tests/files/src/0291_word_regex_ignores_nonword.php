<?php

/**
 * @param string $y, the second arg
 * @param array $x: the first arg
 */
function phpdocCheck291($x, $y) {
    intdiv($x, 2);  // emits error about x being an array
    intdiv($y, 2);  // emits error about y being a string
}
