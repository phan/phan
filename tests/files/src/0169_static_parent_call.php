<?php
error_reporting(E_ALL | E_STRICT);
class A {
    function nonStaticMethod(){}
}

class B extends A {
    static function staticMethod(){
        parent::nonStaticMethod();
    }
}
B::staticMethod();
