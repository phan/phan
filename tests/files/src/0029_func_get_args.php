<?php

function f() {
    $args = func_get_args();
    return $args[0];
}

print f('alpha');
