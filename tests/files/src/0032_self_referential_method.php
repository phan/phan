<?php
class A {

    function f(int $a) : string {
        if (0 >= $a) {
            return 'string';
        }

        return f($a - 1);
    }
}

print (new A)->f(3);
