<?php

class Foo {
    function test() { echo 'first'; }
}
class Foo2 extends Foo {
    function test() { echo 'second'; }
}

$a = rand() % 2 ? new class() extends Foo {} : new class() extends Foo2 {};

$a->test();
