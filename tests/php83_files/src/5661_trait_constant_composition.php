<?php

namespace Issue5661Php83;

// Typed constants that a trait gets from the traits it uses, and `iterable` in the declared types of constants.

// 1. PHP checks the typed constants that a trait gets from the traits it uses as constants of that trait.
// PHP 8.3 and 8.4: "ParentIntA and OuterA define the same constant (A) in the composition of ChildNestedA".
// (PHP 8.5: "Type of ChildNestedA::A must be compatible with ParentIntA::A of type int", see tests/php85_files)
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

// 2. PHP 8.3 to 8.5: "OtherIntB and OuterB define the same constant (B) in the composition of ClassNestedB".
trait InnerStringB {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const string B = 'x';
}

trait OuterB {
    use InnerStringB;
}

trait OtherIntB {
    public const int B = 0;
}

class ClassNestedB {
    use OtherIntB, OuterB;
}
echo ClassNestedB::B;

// 3. PHP 8.3 to 8.5 accept a typed constant that a class gets from a trait through another trait and from its parent class,
// when both declare the same type and value.
class ParentSameC {
    public const int C = 0;
}
echo ParentSameC::C;

trait InnerSameC {
    public const int C = 0;
}

trait OuterSameC {
    use InnerSameC;
}

class ChildSameC extends ParentSameC {
    use OuterSameC;
}
echo ChildSameC::C;

// 4. PHP treats `iterable` as `array|\Traversable`. PHP 8.3 to 8.5 accept these classes.
// The constants of the traits are declared on different lines with the same value.
trait IterableD {
    public const iterable D = [];
}

trait ArrayTraversableD {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const array|\Traversable D = [];
}

class ClassIterableTraitsD {
    use IterableD, ArrayTraversableD;
}
var_dump(ClassIterableTraitsD::D);

trait OuterIterableD {
    use IterableD;
}

class ClassNestedIterableD {
    use OuterIterableD, ArrayTraversableD;
}
var_dump(ClassNestedIterableD::D);

// 5. A constant declared as `?iterable` is covariant with a constant declared as `array|\Traversable|null` (PHP 8.3 to 8.5 accept these).
class ParentArrayTraversableE {
    public const array|\Traversable|null E = null;
    public const array|\Traversable|int F = 0;
}
var_dump(ParentArrayTraversableE::E, ParentArrayTraversableE::F);

class ChildIterableE extends ParentArrayTraversableE {
    public const ?iterable E = null;
    public const iterable|int F = 0;
}
var_dump(ChildIterableE::E, ChildIterableE::F);

interface InterfaceArrayTraversableG {
    public const array|\Traversable G = [];
}
var_dump(InterfaceArrayTraversableG::G);

class ImplementsIterableG implements InterfaceArrayTraversableG {
    public const iterable G = [];
}
var_dump(ImplementsIterableG::G);

trait TraitIterableE {
    public const ?iterable E = null;
}

class ChildTraitIterableE extends ParentArrayTraversableE {
    use TraitIterableE;
}
var_dump(ChildTraitIterableE::E);

// 6. PHP 8.3 to 8.5: "Type of ChildWiderH::H must be compatible with ParentIterableH::H of type Traversable|array".
class ParentIterableH {
    public const iterable H = [];
}
var_dump(ParentIterableH::H);

class ChildWiderH extends ParentIterableH {
    public const array|\Traversable|int H = [];
}
var_dump(ChildWiderH::H);

// 7. PHP 8.3 and 8.4: "ParentIterableI and TraitArrayI define the same constant (I) in the composition of ChildTraitArrayI"
// (the declared types differ; PHP 8.5 accepts it: `array` is covariant with `iterable`, see tests/php85_files).
class ParentIterableI {
    public const iterable I = [];
}
var_dump(ParentIterableI::I);

trait TraitArrayI {
    public const array I = [];
}

class ChildTraitArrayI extends ParentIterableI {
    use TraitArrayI;
}
var_dump(ChildTraitArrayI::I);
