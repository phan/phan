<?php
// Rendering `Foo::{C}` (dynamic class constant fetch) in an issue message used to throw an Error in ASTReverter.
class A5963 {
    public function test(int $x) {}
}
class B5963 extends A5963 {
    public function test(string $x = Foo::{C}) {}
}
