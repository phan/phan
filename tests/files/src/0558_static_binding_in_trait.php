<?php

class C1 {
    use T;
}

class C2 {
    use T;
}

trait T {
    public function getC1() : self
    {
        return new C1();
    }

    public function getC2() : self
    {
        return new C2();  // This is a fatal error when called on an instance of C1
    }

    public function getSelf() : self
    {
        return $this;  // Should not warn
    }

    public function getC1AsT() : T
    {
        // You can't have instances of traits. You can have instances of classes using traits.
        return new C1();  // A return type of T is something Phan should warn about - This return statement would trigger : Uncaught TypeError: Return value of C1::getC1AsT() must be an instance of T, instance of C1 returned in /path/to/phan/trait_binding.php:lineno
    }

}
$c1 = new C1();
var_export($c1->getC1());
var_export($c1->getC2());
