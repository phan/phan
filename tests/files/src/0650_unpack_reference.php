<?php

namespace ns650;

/**
 * @param int &$a
 * @param int &$b
 */
function expects_reference(&$a, &$b) {
}
function returns_array_value() : array {
    return [1, 2];
}
function &returns_array_reference() : array {
    return [1, 2];
}
$elements = [1, 2];
expects_reference(...$elements);
expects_reference(...returns_array_value());  // should warn
expects_reference(...returns_array_reference());  // should not warn
