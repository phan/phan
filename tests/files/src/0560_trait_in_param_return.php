<?php
class C1 {
    use T;
}

trait T {

    public function getC1AsT(T $x, T $other) : T {
        if (rand(0, 1) > 0) {
            return $other;
        }
        // You can't have instances of traits. You can have instances of classes using traits.
        return $x;  // A return type of T is something Phan should warn about - This return statement would trigger Uncaught TypeError: Return value of C1::getC1AsT() must be an instance of T, instance of C1 returned in /path/to/phan/trait_binding.php:lineno
    }
}
