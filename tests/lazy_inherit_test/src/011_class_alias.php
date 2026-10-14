<?php
class C011 extends V011Alias {}
class C011Child extends C011 {}

function expect_string_011(string $s): void {}

function test011(C011 $c, C011Child $d): void
{
    expect_string_011($c->realMethod());
    expect_string_011($d->realMethod());
}
