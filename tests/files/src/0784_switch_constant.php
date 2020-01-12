<?php

namespace NSConstantSwitch;

interface I {
}

class T1 implements I {
    function f1() {
    }
}

class T2 implements I {
    function f2() {
    }
}

class Main {
    /**
     * @param I $arg
     */
    function broken( I $arg ) {
        switch (true) {
            case $arg instanceof T1:
                $arg->f1();
                $arg->f2();  // should warn
                break;
            case $arg instanceof T2:
                $arg->f2(); // PhanUndeclaredMethod Call to undeclared method \I::f2
                $arg->f1();  // should warn
                break;
            case $arg instanceof \Traversable:
            case $arg instanceof \stdClass:
                echo intdiv($arg, 2);  // should infer  \Traversable|\stdClass and warn
                break;
        }
    }

    function testFalse(bool $arg, ?array $values) {
        switch (false) {
            // Check that Phan won't crash. It can't analyze this to infer that $x would be null
            case [$x] = $values:
                var_export(is_string($x));
                break;
            case !$arg:
                echo strlen($arg);
                break;
            case $arg:
                echo strlen($arg);
                break;
        }
    }
    function testZero(?string $x, $y) {
        switch (0) {
            // Check that Phan won't crash. It can't analyze this to infer that $x would be null
            case !$x:
                var_export(is_string($x));
                if ($x) {  // Phan should warn
                    echo "Definitely still truthy\n";
                }
                break;
            case !is_object($y):
                echo strlen($y);  // should infer $y is an object (false == 0 in php)
                break;
            default:
                echo strlen($y);
                break;
        }
    }
}
