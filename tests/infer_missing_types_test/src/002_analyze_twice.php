<?php

class A2 {
    public $x;

    public function setX($obj) {
        $this->x = $obj;
    }

    public function test() {
        // Because Phan analyzes this file twice in --analyze-twice,
        // it is able to infer that $this->x is of type ArrayType,
        // despite the indirection, A2->x being set in the function analyzed after test() is analyzed,
        // and the lack of a documented property type.
        $this->x->missingMethod();
    }

    public function register(A $a) {
        $a->setX(new ArrayObject());
    }
}
