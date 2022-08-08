<?php
namespace NS3;

readonly class Foo {
    public int $bar;
    public $readonlyMissingTypeDeclaration; // should warn
    public function __construct(int $bar) {
        $this->bar = $bar;
        $this->undeclared2 = $bar;
    }
}
$foo = new Foo(123);
$foo->bar = 42;
$foo->undeclaredProperty = 'baz';
var_export($foo->bar);
