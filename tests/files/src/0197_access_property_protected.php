<?php

class A {
        protected $var;
}

class B extends A {
}

class C extends A {
        static function test() {
                $var = new B;
                $var->var = 'hello world';
                echo $var->var;
        }
}

C::test();
