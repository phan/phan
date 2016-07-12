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

    public function h() : self {
        return $this;
    }
}

$a = new A();
$a->g()->f();
$a->h()->f();
$a->g()->z();
$a->h()->z();
