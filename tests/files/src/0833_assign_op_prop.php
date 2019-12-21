<?php

class MyClass {
    public $prop;
    public function __construct() {
        $this->prop = 1;
        $this->prop += 12;
        echo count($this->prop);
    }
}
