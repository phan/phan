<?php

class Example501 {
    const DEFAULT = 'default';
    const X = 55;
    private $propWithStringDefault = self::DEFAULT;
    private $propWithIntDefault = self::X;

    public function __construct() {
        $this->propWithStringDefault = 'x';
        $this->propWithStringDefault = 2;
        $this->propWithStringDefault = new stdClass();
        $this->propWithIntDefault = 3;
        $this->propWithIntDefault = new stdClass();
    }
}
