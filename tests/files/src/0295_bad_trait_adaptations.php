<?php
// Phan should detect and catch some types of misuse of trait adaptations(`insteadof`/`as`)

trait Trait295 {
    public function baz() { }
    public function xyz() { }
}
trait Trait295B {
}

class A295 {
    use Trait295 {
        Trait295::foo as bar;
        Trait295B::xyz insteadof Trait295;
        Trait295C::zz as zzAlias;
    }
}

function test295() {
    $x = new A295();
    $x->baz();
    $x->bar();
    $x->foo();
}
