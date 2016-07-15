<?php

class Test {
    protected $a;
    protected $b;

    public function test() {
        list($x, $y) = [1, 2];

        list( $this->a, $this->b ) = [1, 2];
        $this->other();
    }

    public function other() {
    }
}
