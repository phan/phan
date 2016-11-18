<?php

function closure_generator_tests() {
    $bad_generator = function() : Generator {
        return 1;  // Should print an error
    };

    $bad_generator2 = function() : Iterator {
        return 1;  // Should print an error
    };

    $bad_generator3 = function () : Traversable {
        return 1;  // Should print an error
    };

    /** @return int */
    $bad_generator4 = function () {
        yield;
        return 1;  // Should print an error
    };

    $good_generator = function() : Generator {
        yield 1;
        return 1;
    };

    $good_generator2 = function() use($good_generator) : Generator {
        return $good_generator();
    };

    $good_generator3 = function() : Traversable {
        if (rand()) { return 1; }
        yield;
    };

    /** @return Traversable */
    $good_generator3b = function() {
        if (rand()) { return 1; }
        yield;
    };

    $good_generator3c = function() use($good_generator) : Generator {
        if (rand()) { return 1; }
        yield from $good_generator();
    };

    $good_generator4 = function() use($good_generator) : Generator {
        return (yield from $good_generator());
    };

    /** @return Iterator */
    $good_generator5 = function() {
        yield (new stdClass());
    };
}
