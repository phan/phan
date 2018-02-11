<?php

// Regression test for Phan failing to hydrate classes if properties used inherited constants

class A415 {
    const CONSTNAME = 2;
}

class C415 extends B415 {
    public $otherProperty = D415::CONSTNAME;
}

class D415 extends C415 {
}

class B415 extends A415 {
    public $property = D415::CONSTNAME;
    public $badProperty = D415::MISSING_CONSTNAME;
}
