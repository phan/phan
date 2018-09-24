<?php
// Phan should detect and catch some types of misuse of trait adaptations(`insteadof`/`as`)

trait Trait56 {
}
trait Trait56B {
}

class A56 {
    use Trait56 {
        Trait56B::${0} insteadof Trait56;
    }
}
