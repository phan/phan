<?php
// The interface is imported first, so the parent's name() is not lazy in C001 (multi_provider); size() is.
interface I001
{
    public function name(): string;
}
class C001 extends V001Base implements I001 {}

function expect_int_001(int $i): void {}

function test001(C001 $c, I001 $i): void
{
    expect_int_001($c->name());
    expect_int_001($i->name());
    expect_int_001($c->size());
    $c->sizes();
}

/**
 * @suppress PhanTypeMismatchArgument
 */
function test001_suppressed(C001 $c): void
{
    expect_int_001($c->name());
}

/**
 * @suppress PhanTypeMismatchArgument (unused: size() returns int)
 */
function test001_unused_suppression(C001 $c): void
{
    expect_int_001($c->size());
}
