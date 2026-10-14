<?php
class E019 extends \Exception {}
class E019Child extends E019 {}
class A019 extends \ArrayObject {}

function expect_int_019(int $i): void {}
function expect_string_019(string $s): void {}

function test019(E019 $e, A019 $a, E019Child $c): void
{
    expect_int_019($e->getMessage());
    expect_int_019($c->getCode());
    expect_string_019($c->getLine());
    expect_string_019($a->count());
    $e->getMessages();
}
