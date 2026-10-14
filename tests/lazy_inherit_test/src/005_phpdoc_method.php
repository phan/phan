<?php
/**
 * @method string magic(int $x)
 */
class C005 extends V005Mid {}

function expect_int_005(int $i): void {}

function test005(C005 $c): void
{
    expect_int_005($c->magic(1));
    expect_int_005($c->parentMagic());
    expect_int_005($c->plain('extra'));
}
