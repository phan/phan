<?php
// The (at)mixin is imported in the class phase, which looks up the lazy slots of S004.
class S004 extends V004Base {}

/** @mixin S004 */
class M004 {}

function expect_int_004(int $i): void {}

function test004(M004 $m, S004 $s): void
{
    expect_int_004($m->mixed2('x'));
    expect_int_004($m->mixed1(5));
    expect_int_004($s->mixed2(1));
}
