<?php

class Example500 {
    /** @var string[] */
    private $propWithArrayDefault = [];

    /** @var string[] */
    private $propSetInConstructor;

    public function __construct() {
        $this->propSetInConstructor = [];
    }

    public function badAssign(int $val) {
        $this->propWithArrayDefault[] = $val;
    }

    public function badAssign2(int $val) {
        $this->propSetInConstructor[] = $val;
    }
}
