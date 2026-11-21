<?php

namespace Issue5661Php85;

// Constants that a trait gets from the traits it uses, and `iterable` in the declared types of constants, from PHP 8.5 on.

// 1. From PHP 8.5 on, a trait constant overrides a constant of the parent class like a constant of the class would, also when the class
// gets it from a trait through another trait. PHP 8.5: "Type of ChildNestedA::A must be compatible with ParentIntA::A of type int"
// (PHP 8.3 and 8.4: "ParentIntA and OuterA define the same constant (A) in the composition of ChildNestedA", see tests/php83_files).
class ParentIntA {
    public const int A = 0;
}
echo ParentIntA::A;

trait InnerStringA {
    public const string A = 'x';
}

trait OuterA {
    use InnerStringA;
}

class ChildNestedA extends ParentIntA {
    use OuterA;
}
echo ChildNestedA::A;

// 2. PHP 8.5 accepts an untyped constant with another value in a trait used through another trait
// (PHP 8.2 to 8.4: "ParentB and OuterB define the same constant (B) in the composition of ChildNestedB").
class ParentB {
    public const B = 0;
}
echo ParentB::B;

trait InnerB {
    public const B = 1;
}

trait OuterB {
    use InnerB;
}

class ChildNestedB extends ParentB {
    use OuterB;
}
echo ChildNestedB::B;

// 3. A constant declared as `?iterable` in a trait is covariant with a constant declared as `array|\Traversable|null` in the parent class,
// and `array` is covariant with `iterable` (PHP 8.5 accepts these).
class ParentArrayTraversableC {
    public const array|\Traversable|null C = null;
}
var_dump(ParentArrayTraversableC::C);

trait TraitIterableC {
    public const ?iterable C = null;
}

class ChildTraitIterableC extends ParentArrayTraversableC {
    use TraitIterableC;
}
var_dump(ChildTraitIterableC::C);

trait OuterIterableC {
    use TraitIterableC;
}

class ChildNestedIterableC extends ParentArrayTraversableC {
    use OuterIterableC;
}
var_dump(ChildNestedIterableC::C);

class ParentIterableD {
    public const iterable D = [];
}
var_dump(ParentIterableD::D);

trait TraitArrayD {
    public const array D = [];
}

class ChildTraitArrayD extends ParentIterableD {
    use TraitArrayD;
}
var_dump(ChildTraitArrayD::D);
