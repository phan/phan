<?php
// S013Pin imports beta() from the interface first, so its parent import looks up S013Mid::beta in the class phase
// (which creates that copy): S013ChildBefore was imported before that, S013ChildAfter after.
interface I013
{
    public function beta(): string;
}
class S013Mid extends V013Mid {}
class S013ChildBefore extends S013Mid {}
class S013Pin extends S013Mid implements I013 {}
class S013ChildAfter extends S013Mid {}
class S013Leaf extends S013ChildBefore {}

function expect_int_013(int $i): void {}

function test013(S013ChildBefore $b, S013ChildAfter $a, S013Leaf $l, S013Mid $m, S013Pin $p): void
{
    expect_int_013($b->beta());
    expect_int_013($a->beta());
    expect_int_013($l->beta());
    expect_int_013($m->beta());
    expect_int_013($p->beta());
    expect_int_013($l->gamma());
    expect_int_013($m->gamma());
    echo $l->alpha()->x;
}
