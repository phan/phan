<?php
// Phan should detect and catch some types of misuse of trait adaptations(`insteadof`/`as`)

trait Trait55 {
    public function baz() { }
    public function xyz() { }
}

class A55 {
    use Trait55 {
        Trait55C::${0} as zzAlias;
    }
}
