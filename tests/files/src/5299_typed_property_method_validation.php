<?php

// Test case for issue #5299 - Method calls on typed properties should validate against declared type, not inferred type

interface Interface2 {
}

interface Interface1 extends Interface2 {
    public function someMethod1();
}

class Class1 implements Interface1 {
    public function someMethod1() {
        echo "Hello there";
    }
}

class Class2 {
    protected Interface2 $my_internal_instance;

    public function __construct() {
        // Assignment is allowed: Class1 implements Interface1 which extends Interface2
        $this->my_internal_instance = new Class1();
    }

    public function someMethod1() {
        // ERROR: Interface2 does not have someMethod1(), even though we assigned Class1 to it
        return $this->my_internal_instance->someMethod1();
    }
}

// This class implements Interface2 but doesn't have someMethod1()
class Class4 implements Interface2 {
}

class Class3 extends Class2 {
    public function setWrong() {
        // This is allowed at assignment time (Class4 implements Interface2)
        // but demonstrates the problem at runtime
        $this->my_internal_instance = new Class4();
    }
}

$instance = new Class3();
$instance->setWrong();
// Runtime error: Class4 doesn't have someMethod1()
$instance->someMethod1();
