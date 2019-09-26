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
}
