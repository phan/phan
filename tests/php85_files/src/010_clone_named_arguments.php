<?php

namespace CloneNamedArguments;

class O
{
    public int $a = 1;
}

function test(O $o): void
{
    $x = clone(object: $o);
    $y = clone(withProperties: ['a' => 2], object: $o);
    echo $x->missing;
    echo $y->alsoMissing;
    echo clone(object: 42);
}
