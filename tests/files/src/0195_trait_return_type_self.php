<?php

trait B {
    public function g(): self
    {
        return $this;
    }
}

class A {
    use B;

    public function f()
    {
    }
}

$a = new A();
$a->g()->f();
