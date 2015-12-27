<?php

interface I {
    public function f() : I;
}

class C {
    public function g() {
        $v = new self;
        if ($this instanceof I) {
            $v = $this->f();
        }
        return $v;
    }
}

class A extends C implements I {
    public function f(): I {
        return $this;
    }
}

$ancestor = new A();
$ancestor->g();
