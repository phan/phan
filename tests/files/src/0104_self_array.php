<?php
class A {
    /** @return self[] */
    function f() {
        return [new self(), new self()];
    }

    function g() {
        foreach ($this->f() as $c) {
            $c->g();
        }
    }
}
