<?php
namespace TypedProperties;

class A {
    /** Description of this property */
    public ?int $a;
    /** @var string description of this string */
    public string $b;
    public A $c;
    private int $d;
    public function __construct(?int $a, string $b, SubClass $c) {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
        $this->c = $this;
        $this->d = 5;
    }
}
$a = new A();
echo count($a->a);
echo count($a->b);
echo count($a->c);

class SubClass extends A {
    /** Regression test for #4426 - should not trigger warnings about incompatible types since A::$d is private */
    private $d;
}
