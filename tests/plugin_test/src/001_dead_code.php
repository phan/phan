<?php

if (rand() % 2 == 1) {
    function duplicateFnA() {
        return 42;
    }

    function duplicateFnB() {
        return 'x';
    }

    define('Const1A', 'x');
    define('Const1B', 1);

    class DuplicateClass001 {
        const C = true;

        public static $static_prop1 = '002';
        public static $static_prop2;

        public $instance_prop1 = 'a';
        public $instance_prop2 = 'A';

        public static function f1() {}
        public static function f2() {}
        public static function f3() {}
    }
} else {
    function duplicateFnA() {
        return 15;
    }

    function duplicateFnB() {
        return 'y';
    }
    define('Const1A', 'x');
    define('Const1B', 2);

    class DuplicateClass001 {
        const C = false;
        const D = false;

        public static $static_prop1;
        public static $static_prop2;

        public $instance_prop1;
        public $instance_prop2;

        public static function f1() {}
        public static function f2() {}
        public static function f4() {}
    }
}

function test001() {
    duplicateFnA();
    echo Const1A . "\n";
    $x = new DuplicateClass001();
    printf("Class const: %s\n", DuplicateClass001::C . "\n");
    DuplicateClass001::f1();
    printf("Static prop: %s\n", DuplicateClass001::$static_prop1 . "\n");
    echo ($x->instance_prop1) . "\n";
}
test001();
