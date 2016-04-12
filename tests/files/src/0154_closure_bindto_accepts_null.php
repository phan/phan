<?php
class A {
    function getClosure(): Closure {
        return function() {};
    }
}

$ob1 = new A();

$cl = $ob1->getClosure();
$cl2 = $cl->bindTo(null);
