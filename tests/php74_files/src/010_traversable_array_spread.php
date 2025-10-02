<?php

/**
 * @param Generator<float, stdClass> $gf
 * @param iterable<float, int> $if
 * @param array<string, int> $as
 * @param Generator<string, stdClass> $gs
 * @param iterable<string, int> $is
 * @param Generator<void, stdClass> $gv
 * @param Generator<mixed, stdClass> $gm
 */
function test_array_spread(Generator $gf, iterable $if, array $as, Generator $gs, iterable $is, Generator $gv, Generator $gm) {
    var_export([
        ...$gf,
        ...$if,
        ...$as,
        ...$gs,
        ...$is,
        ...$gv,
        ...$gm,
    ]);
}
