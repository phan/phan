<?php

function makeObject(string $name) : object {
    $x = new $name();
    if (!is_object($x)) {  // should warn about redundant check
        throw new RuntimeException("Not possible");
    }
    return $x;
}
makeObject('stdClass');
function makeObject2() : stdClass {
    $name = 'ArrayObject';
    $x = new $name();
    if (!is_object($x)) {  // should warn about redundant check
        throw new RuntimeException("Not possible");
    }
    return $x;
}
var_export(makeObject2());
