<?php

enum A23 {
    case Single;
}

enum B23: int {
    case Single = 1;
}

// This will throw
class Invalid23 implements UnitEnum, Traversable {
    public static function cases(): array {
        return [];
    }
}

function test23(UnitEnum $a, BackedEnum $b) {
    var_dump($a->name, $b->name);
    var_dump($a->value, $b->value);
    $a->name = 'bad';
    $b->name = 'bad';
    $b->value = 'bad';
    $b->extra = 'bad';
    var_dump(clone $a, clone $b);
}
test23(A23::Single, B23::Single);
