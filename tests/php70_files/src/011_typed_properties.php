<?php
class Foo {
    public callable $c1, $c2;
    public int $bar;
    public self $foo;
}
$foo = new Foo();
$foo->bar = 2;
$foo->foo = $foo;
$foo->c1 = 'strlen';
$foo->c2 = function () { return 'x'; };
var_export([$foo->bar, $foo->foo, $foo->c1, ($foo->c2)()]);
