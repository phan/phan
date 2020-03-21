<?php

function f168(string $s) : ArrayObject {
    return new ArrayObject([strlen($s)]);
}
function g168(string $s) : ArrayObject {
    return new ArrayObject([print($s)]);
}
function h168() : ArrayObject {
    return new ArrayObject;
}
f168('test');
g168('other');
h168();
