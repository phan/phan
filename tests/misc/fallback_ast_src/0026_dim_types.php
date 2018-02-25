<?php

/*
class A extends ArrayObject {}

$a = new A;
$a[] = 5;
$x = $a[0];


$b = [1, 2, 3];
$x = $b[1];
print "$x\n";


$c = 'string';
$x = $c[2];
print "$x\n";
 */

class B {
    function f(int $a) : int {
        return $a;
    }

    // @return int[]
    function g() {
        return [1, 2, 3];
    }

    /**
     * @return string[]
     */
    function h() {
        return ['a', 'b', 'c'];
    }
}

$e = new B;
/*
$x = $e->f($e->g()[1]);
print "$x\n";
 */

// Should be of type string
$f = $e->h()[2];
$x = $e->f($f);
print "$x\n";
