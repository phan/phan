<?php

// Regression test for https://github.com/phan/phan/issues/5544
// A child class that redeclares a property (e.g. to widen visibility, or via
// @inheritDoc) and declares no type of its own should inherit the ancestor's
// declared/inferred type.
class A5931 {
    protected $property;
    /** @var string */
    protected $property2;

    public function __construct() {
        $this->property = 'a';
        $this->property2 = 'a2';
    }

    public function runA() {
        $p = $this->property;
        $p2 = $this->property2;
        '@phan-debug-var $p, $p2';
    }
}

class B5931 extends A5931 {
    public $property;
    /** @inheritDoc */
    public $property2;

    public function runB() {
        $p = $this->property;
        $p2 = $this->property2;
        '@phan-debug-var $p, $p2';
    }
}

// A child that declares its own type must keep it (not widen to the ancestor's type).
class C5931 extends A5931 {
    /** @var int */
    public $property2;

    public function runC() {
        $p2 = $this->property2;
        '@phan-debug-var $p2';
    }
}
