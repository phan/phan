<?php

/** @return Generator<int,string> */
function yields_correct_generator() {
    yield 2 => 'x';
}

/** @return Generator<void,string> */
function yields_generator_without_key() {
    yield 'x';
}

/** @return Generator<int,stdClass> */
function yields_generator_with_wrong_value() {
    yield 0 => new stdClass();
}

/**
 * @return Generator<int,string>
 */
function test_yield_from() {
    yield from [2 => 'x'];
    yield from [2 => 4];
    yield from ['x' => 'y'];
    yield from [2 => 'y', 3 => new stdClass()];

    yield from yields_correct_generator();
    yield from yields_generator_without_key();
    yield from yields_generator_with_wrong_value();

    yield from 'not valid';
    yield from new stdClass();
}
