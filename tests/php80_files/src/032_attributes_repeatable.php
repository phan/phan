<?php

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
#[
    Attribute(Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE),
]
class Route32 {
    public function __construct(public string $path) {}
}

#[Attribute(
    Attribute::TARGET_FUNCTION,
)]
class Single32 {
    public function __construct(public string $path) {}
}

#[Route32('/a')]
#[Route32('/a/b')]
function test32($arg) {
    var_export($arg);
}

#[Single32('/a')]
#[single32('/a/b')]
function test32_bad($arg) {
    var_export($arg);
}
test32('/a/b');
test32('/a');
var_export((new ReflectionFunction('test32'))->getAttributes()[1]->newInstance());
var_export((new ReflectionFunction('test32_bad'))->getAttributes()[1]->newInstance());
