<?php

class A329 {
    public static $prop = 3;
    public $static_prop = 3;

    function foo() {
        var_dump($this->prop);
        var_dump(self::$instance_prop);
    }
}
