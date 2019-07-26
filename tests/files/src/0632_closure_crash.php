<?php
// XXX: In phpdoc, only Closure is accepted as an implementation detail (case-sensitive)
function foo(): closure {
    if (rand() % 2 > 0) {
        return function() {};
    }
    return false;
}

/** @return closure */
function foo2() {
    if (rand() % 2 > 0) {
        return function() {};
    }
    return false;
}

function foo_callable(): Callable {
    if (rand() % 2 > 0) {
        return 'strlen';
    }
    return false;
}

$bar = function() {
};
