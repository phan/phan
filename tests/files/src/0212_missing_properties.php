<?php

class A {
    public $a;
}

$a = new A();

// Generates no message
print "{$a->asdf}";

// Generates no message
print "{$a->asdf}";

// Generates "PhanUndeclaredProperty Reference to undeclared property \A->asdf"
print $a->asdf;
