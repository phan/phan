<?php

/**
 * @param string $first @unused-param description
 * @param string $second @unused not recognized
 * @param string $third description @phan-unused-param
 * @param string $fourth description
 */
function test187($first, $second, $third, $fourth) {
    echo __FUNCTION__ . "\n";
}

test187('a', 'b', 'c', 'd');
