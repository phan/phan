<?php
namespace NS24;

class Foo {
    public readonly int $bar;
    public readonly string $baz;
    public readonly $readonlyMissingTypeDeclaration; // should warn
    public function __construct(int $bar, int $baz) {
        $this->bar = $bar;
        $this->baz = $baz;
    }
}
$foo = new Foo(123);
$foo->bar = 42;
var_export($foo->bar);
