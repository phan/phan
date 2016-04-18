<?php

function f(resource $var) {}

function g($var) : resource {
    if (is_array($var)) {
        f($var);
    } elseif (is_bool($var)) {
        f($var);
    }

    return $var;
}

g(['a', 'b', 'c']);
