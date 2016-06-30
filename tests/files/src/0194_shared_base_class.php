<?php

class Base {
    protected $var;

    protected function func() {
    }
}

class A extends Base {
}

class B extends Base {
    public function test() {
        $x = new A;
        $x->var = 'var';
        $x->func();
        $this->var = 'var';
        $this->func();
    }
}

class C {
    public function test() {
        $x = new A;
        $x->var = 'var';
        $x->func();
    }
}
