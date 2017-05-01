<?php
/** @param resource $var */
function f($var) {}
/** @return resource */
function g($var) {
    if (is_array($var)) {
        f($var);
    } elseif (is_bool($var)) {
        f($var);
    }

    return $var;
}

g(['a', 'b', 'c']);
