<?php
class X {
    /** @var array */
    public $x;
    /** @var array */
    public $y;

    public function __construct(array $arg) {
        $this->y = $this->x;
        $this->x = $arg;
    }
}
