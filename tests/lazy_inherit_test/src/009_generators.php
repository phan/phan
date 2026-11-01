<?php
class C009 extends V009Base {}
class C009Child extends C009 {}

function expect_string_009(string $s): void {}

function test009(C009 $c, C009Child $d): void
{
    foreach ($c->gen() as $v) {
        expect_string_009($v);
    }
    foreach ($c->genIter() as $v) {
        expect_string_009($v);
    }
    expect_string_009($c->genTyped());
    expect_string_009($d->genTyped());
    expect_string_009($d->notGen());
}
