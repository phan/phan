<?php
const XStatic = rand(0, 10);
class XStatic {
    public $a = rand(0, 10);
    function teststatic() {
        static $a = rand(0, 10);
    }
}
