<?php

namespace NS942;

use Countable;
use Traversable;

interface IntInvokable {
    public function __invoke(int $arg): void {
        var_dump($arg);
    }
}

function test1(Countable $value) {
    if ($value instanceof IntInvokable) {
        var_dump($value());  // missing arg
    }
}

function test2(Countable $value) {
    if ($value instanceof Traversable) {
        $value();  // missing arg
    }
}
