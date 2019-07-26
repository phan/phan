<?php
namespace TypedProperties;

class A {
    /** Description of this property */
    public ?int $a;
    /** @var string description of this string */
    public string $b;
    public A $c;
    public function __construct(?int $a, string $b, SubClass $c) {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
        $this->c = $this;
    }
}
$a = new A();
echo count($a->a);
echo count($a->b);
echo count($a->c);

class SubClass extends A {
}
