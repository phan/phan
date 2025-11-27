<?php

/**
 * Test for Issue #5391: PhanUnreferencedPrivateProperty false positive when
 * a trait method references a property defined by the class using the trait.
 *
 * @property string $prop
 */
trait MyTrait5391 {
    public function printProp(): void {
        echo $this->prop;
    }
}

class MyClass5391 {
    use MyTrait5391;

    private string $prop = 'foo';

    public function __construct() {
        $this->prop = 'bar';  // Write reference
    }
}

$x = new MyClass5391();
$x->printProp();
