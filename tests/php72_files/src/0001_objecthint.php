<?php

function object_test(object $x, ?object $y) : object {
    if ($y) {
        return $y;
    } else if ($x->prop) {
        return $x;
    } else {
        return null;  // should warn
    }
}
object_test(new stdClass(), new SimpleXMLElement('<a>b</a>'));
object_test(null, null);  // should warn about the first
object_test([], []);  // should warn about the first





function nullableobject_test(?object $y) : ?object {
    if ($y->prop ?? false) {
        return $y;
    } else if (rand() % 2) {
        return $y;
    }
    return 'invalid';
}

$c = new object();  // wrong
