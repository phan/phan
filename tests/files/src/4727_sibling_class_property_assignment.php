<?php

class Base {}
class A1 extends Base {}
class A2 extends Base {}
class SubA1 extends A1 {}

class C {
    public A1 $prop1;
    /** @var A1 */
    public $prop2;
}

// Test basic sibling assignment - should fail
function test_sibling_assignment() {
    $c = new C();
    $c->prop1 = new A2();  // Should warn: PhanTypeMismatchPropertyReal
    $c->prop2 = new A2();  // Should warn: PhanTypeMismatchPropertyProbablyReal
}

// Test valid subclass assignment - should pass
function test_subclass_assignment() {
    $c = new C();
    $c->prop1 = new SubA1();  // OK: SubA1 is subclass of A1
    $c->prop2 = new SubA1();  // OK: SubA1 is subclass of A1
}

// Test valid same-class assignment - should pass
function test_same_class_assignment() {
    $c = new C();
    $c->prop1 = new A1();  // OK
    $c->prop2 = new A1();  // OK
}

// Test with interfaces
interface IBase {}
interface IA1 extends IBase {}
interface IA2 extends IBase {}

class D {
    /** @var IA1 */
    public $interfaceProp;
}

function test_interface_sibling() {
    $d = new D();
    $obj = new class implements IA2 {};
    $d->interfaceProp = $obj;  // Should warn: IA2 is not IA1
}
