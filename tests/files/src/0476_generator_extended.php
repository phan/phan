<?php

namespace NS476;

/**
 * @return \Generator<int,string,array,bool>
 */
function generator_4() : \Generator {
    $x = yield 2 => 'value';
    echo strlen($x);  // should warn

    yield false;

    if (rand() % 2 > 0) {
        return true;
    } else {
        return 'invalid';  // should warn
    }
}

/**
 * @return \Generator<int,string,bool>
 */
function generator_3() : \Generator {
    $x = yield 2 => 'value';
    echo strlen($x);  // should warn

    yield false;

    if (rand() % 2 > 0) {
        return true;
    } else {
        return 'invalid';  // should warn
    }
}

/**
 * @return \Generator<int,string,array,void>
 */
function generator_void() : \Generator {
    yield 2 => 'x';
    if (rand() % 2 > 0) {
        return;
    }
    return 'invalid';  // should warn
}
