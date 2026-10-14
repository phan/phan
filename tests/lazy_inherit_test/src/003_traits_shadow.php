<?php
// T003::setUp overrides the lazy setUp of B003 in D003, and of B003DeepMid in D003Deep:
// V003Base::setUp narrows its parameter with a doc comment.
// The hydration of D003Deep creates B003DeepMid::setUp (its parent import conflicts with the trait's setUp), and the
// override check of D003Deep::setUp in the method phase creates B003Deep::setUp, which comes before it in eager order
// (the nested pipeline case).
trait T003
{
    public function setUp(array $values): void {}
    public function hello(): string { return 'trait'; }
}
trait T003b
{
    public function hello(): string { return 'b'; }
    public function bye(): int { return 1; }
}
abstract class B003 extends V003Base {}
class D003 extends B003
{
    use T003, T003b {
        T003::hello insteadof T003b;
        T003b::hello as helloB;
    }
}

abstract class B003Deep extends V003Base {}
abstract class B003DeepMid extends B003Deep {}
class D003Deep extends B003DeepMid
{
    use T003;
}

function expect_int_003(int $i): void {}

function test003(D003 $d): void
{
    $d->setUp(['x']);
    expect_int_003($d->hello());
    expect_int_003($d->helloB());
    expect_int_003($d->stay());
    echo $d->bye()->x;
}

function test003_deep(D003Deep $d): void
{
    $d->setUp(['x']);
    expect_int_003($d->hello());
    expect_int_003($d->bye());
}
