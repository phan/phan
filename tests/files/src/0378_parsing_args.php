<?php
/**
 * Tests edge cases in parsing union types.
 * @param float[]string $blah this should be parsed as (at)param float[] - The following token `string` is nonsense.
 * @param int[] $y this is parseable
 */
function test378($x, $y) {
}

test378(new stdClass(), new stdClass());

/**
 * Unrecognized annotations are ignored.
 * @parameter $x (Should not parse union type of eter, Phan requires a space or word boundary)
 */
function test378b($x) {
}
test378b(2);
