<?php

// @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicMethod

// Test that track_all_inferred_types causes:
// 1. Concrete types to be accumulated on interface-typed properties
// 2. Return types to be widened with inferred concrete types

interface Animal5920 {}

class Cat5920 implements Animal5920 {}

class Dog5920 implements Animal5920 {}

class Zoo5920 {
    private Animal5920 $animal;

    public function __construct() {
        $this->animal = new Cat5920();
    }

    public function switchAnimal(): void {
        $this->animal = new Dog5920();
    }

    public function getAnimal(): Animal5920 {
        return new Cat5920();
    }

    public function testPropertyAccumulation(): void {
        // With track_all_inferred_types, $a should have Cat5920 and Dog5920
        // accumulated alongside the declared Animal5920 type.
        $a = $this->animal;
        '@phan-debug-var $a';
    }

    public function testReturnTypeWidening(): void {
        // With track_all_inferred_types, getAnimal()'s return type should be
        // widened to include the inferred Cat5920 type.
        $b = $this->getAnimal();
        '@phan-debug-var $b';
    }
}
