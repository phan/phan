<?php

namespace Issue5661Php83;

// Typed constants that a trait gets from the traits it uses, `iterable` in the declared types of constants, and their values.

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

// 8. PHP converts an int value of a constant whose declared type accepts float and not int to a float (`const float J = 1;` is 1.0).
// PHP 8.3 to 8.5 accept ClassFloatValues.
trait IntToFloatValues {
    public const float J = 1;
    public const ?float K = -2;
    public const float|string L = 1 + 2;
}

trait FloatValues {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const float J = 1.0;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const ?float K = -2.0;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const float|string L = 3.0;
}

class ClassFloatValues {
    use IntToFloatValues, FloatValues;
}
var_dump(ClassFloatValues::J, ClassFloatValues::K, ClassFloatValues::L);

// 9. PHP does not convert the value when the declared type accepts int: PHP 8.3 to 8.5 reject ClassIntOrFloat
// ("IntOrFloatFirst and IntOrFloatSecond define the same constant (M) in the composition of ClassIntOrFloat").
trait IntOrFloatFirst {
    public const int|float M = 1;
}

trait IntOrFloatSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const int|float M = 1.0;
}

class ClassIntOrFloat {
    use IntOrFloatFirst, IntOrFloatSecond;
}
var_dump(ClassIntOrFloat::M);

// 10. A constant reached through a reference has the converted value: PHP 8.3 to 8.5 reject ClassConvertedReference
// ("ConvertedReferenceFirst and ConvertedReferenceSecond define the same constant (N) ...": 1.0 and 1).
class FloatHolder {
    public const float VALUE = 1;
}
echo FloatHolder::VALUE;

trait ConvertedReferenceFirst {
    public const N = FloatHolder::VALUE;
}

trait ConvertedReferenceSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const N = 1;
}

class ClassConvertedReference {
    use ConvertedReferenceFirst, ConvertedReferenceSecond;
}
var_dump(ClassConvertedReference::N);

// 11. PHP converts the value of a constant when it evaluates the value at compile time. It does not evaluate `self::ONE` in a trait
// at compile time, and compares 1 with 1.0: PHP 8.3 to 8.5 reject ClassRuntimeValue ("RuntimeValueFirst and RuntimeValueSecond ...").
// Phan does not know whether PHP replaces a class constant at compile time, and does not report a converted value with a class constant.
trait RuntimeValueFirst {
    public const ONE = 1;
    public const float O = self::ONE;
}

trait RuntimeValueSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const float O = 1.0;
}

class ClassRuntimeValue {
    use RuntimeValueFirst, RuntimeValueSecond;
}
var_dump(ClassRuntimeValue::ONE, ClassRuntimeValue::O);

// 12. PHP replaces `IntHolder::ONE` at compile time and converts the value of the constant P that ClassNestedConverted gets
// through OuterConverted to 1.0: PHP 8.3 to 8.5 accept ClassNestedConverted. Phan does not report it.
class IntHolder {
    public const ONE = 1;
}

trait InnerConverted {
    public const float P = IntHolder::ONE;
}

trait OuterConverted {
    use InnerConverted;
}

trait FloatP {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const float P = 1.0;
}

class ClassNestedConverted {
    use OuterConverted, FloatP;
}
var_dump(ClassNestedConverted::P);
