<?php

function read289(resource $x) {
    fread($x, 1024);
}

function realResource289() {
    $x = fopen('/tmp/test', 'r');
    fread($x, 1024);  // don't warn
    echo intdiv($x, 2);  // warn, this is a resource
}

// Should warn because this uses an undeclared class with name object
function readObject289(object $x) {
}

// Should warn because this uses an undeclared class with name mixed
function readMixed289(mixed $x) {
}
