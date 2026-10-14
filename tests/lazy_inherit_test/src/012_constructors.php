<?php
class C012A extends V012WithCtor {}
class C012B extends V012NoCtor {}

function expect_string_012(string $s): void {}

function test012(): void
{
    $a = new C012A();
    $b = new C012B(1);
    $c = new C012A('x');
    expect_string_012($a->m());
    expect_string_012($b->m());
    expect_string_012($c->m());
}
