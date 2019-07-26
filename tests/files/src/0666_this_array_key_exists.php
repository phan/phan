<?php

class ArrayKeyExists666 {
    /** @var array{0:string} */
    public $x = ['test'];

    public function test() {
        if (array_key_exists(1, $this->x)) {
            echo strlen($this->x);
        }
        if (array_key_exists(0, $this->x)) {
            echo intdiv($this->x[0], 2);
        } else {
            // should be unset
            echo intdiv($this->x[0], 2);
        }
    }
}
