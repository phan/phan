<?php
trait B {
    abstract function f();
    /** @return static (Phan recognizes that `static` is a narrower form of `self`) */
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

    /** @return static */
    public function h() : self {
        return $this;
    }
}

$a = new A();
$a->g()->f();
$a->h()->f();
$a->g()->z();
$a->h()->z();
