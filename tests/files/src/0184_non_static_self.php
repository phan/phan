<?php
class A {
    function nonStaticMethod(){
        echo "AAAAAAAAA";
    }
    function testA(){
        self::nonStaticMethod();
    }
}
$a = new A();
$a->testA();
