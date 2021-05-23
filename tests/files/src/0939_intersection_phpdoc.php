<?php

// Test that Phan can tolerate and detect invalid intersection types

/**
 * @param int&bool $value this is an impossible combination of values
 * @param stdClass&ArrayObject $object
 * @return SplObjectStorage&Error
 */
function test939($value, object $object): object {
    return $value;
}
test939(123, new stdClass()); // Phan should not crash analyzing this code.

class C939 {
    /** @var stdClass&false */
    public $var;
}

