<?php

class Foo660 {
    /** @var ?int description */
    public $prop;

    /**
     * @var ?stdClass
     */
    public $possibly_null;

    // Warning messages include Phan's inferred types
    public function example() {
        $this->prop = 2;
        echo strlen($this->prop);
    }

    public function example2() {
        if ($this->possibly_null) {
            echo strlen($this->possibly_null);
        } else {
            echo "this is null\n";
            echo strlen($this->possibly_null);
        }
    }
}
