<?php

function unknownType() {
    $v = ['a', 42, new DateTime];
    return $v[1];
}

function f() : DateTime {
    if (true) { return; }
    if (true) { return unknownType(); }
    return new DateTime;
}

function g() : int {
    return;
}
