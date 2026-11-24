<?php

class ClonableExample {
    public int $prop = 0;
}

$object = new ClonableExample();

clone($object, ['prop' => 42]);
$nested = clone(clone($object), ['a' => clone($object, ['b' => 2])]);
$multiline = clone(
    $object,
    [
        'a' => 1,
    ]
);
$named = clone(withProperties: ['a' => 1], object: $object);
$unary = clone
    $object;
$after = clone $object
;
