<?php

// Regression test for https://github.com/phan/phan/issues/5044

/* @phan-file-suppress PhanUnreferencedClass */

class ConcreteClass implements MyInterface {
    public function doSomething( $module ) {
        if ( !$module instanceof Class5 ) {
            return;
        }
    }
}

interface MyInterface {
    public function doSomething( Class1 $module );
}

interface Interface0 {}

interface Interface1 extends Interface0 {}

abstract class Class0 implements Interface1 {
    public function getObject(): Interface1 {
        return new static;
    }
}

class Class1 extends Class0 {}
class Class2 extends Class1 {}
class Class3 extends Class2 {}
class Class4 extends Class3 {}
class Class5 extends Class4 {}

