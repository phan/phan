<?php
// LazyOpsTestPlugin targets V018Base::make/run and C018Child::make/run.
class C018Child extends V018Base
{
    public function reportedByLazyOpsTestPlugin(): void {}
}
class C018Other extends V018Base {}
class C018Grand extends C018Child {}

function expect_string_018(string $s): void {}

function test018(C018Child $c, C018Other $o, C018Grand $g, V018Base $b): void
{
    expect_string_018($c->make());
    expect_string_018($o->make());
    expect_string_018($g->make());
    expect_string_018($b->make());
    $c->run(1, 2);
    $o->run();
    $g->run(3);
    $c->map(fn(string $s): string => $s);
    $g->map('strlen');
}
