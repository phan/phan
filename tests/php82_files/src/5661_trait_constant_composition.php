<?php

namespace Issue5661Php82;

// Constants that a trait gets from the traits it uses, and the values of constants with the same name in a composition.

// 1. PHP checks the constants that a trait gets from the traits it uses as constants of that trait.
// PHP 8.2 to 8.4: "ParentNestedA and OuterNestedA define the same constant (A) in the composition of ChildNestedA"
// (PHP 8.5 accepts it: a trait constant overrides a constant of the parent class).
class ParentNestedA {
    public const A = 0;
}
echo ParentNestedA::A;

trait InnerNestedA {
    public const A = 1;
}

trait OuterNestedA {
    use InnerNestedA;
}

class ChildNestedA extends ParentNestedA {
    use OuterNestedA;
}
echo ChildNestedA::A;

// 2. PHP 8.2 to 8.5: "DeepOuterB and OtherB define the same constant (B) in the composition of ClassDeepB".
trait DeepInnerB {
    public const B = 1;
}

trait DeepMiddleB {
    use DeepInnerB;
}

trait DeepOuterB {
    use DeepMiddleB;
}

trait OtherB {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const B = 2;
}

class ClassDeepB {
    use DeepOuterB, OtherB;
}
echo ClassDeepB::B;

// 3. PHP 8.2 to 8.5: "ClassOwnC and OuterC define the same constant (C) in the composition of ClassOwnC".
trait InnerC {
    public const C = 1;
}

trait OuterC {
    use InnerC;
}

class ClassOwnC {
    use OuterC;
    public const C = 2;
}
echo ClassOwnC::C;

// 4. PHP 8.2 to 8.5 accept these: the same constant reached through two traits, a class using a trait and the trait that it uses,
// a trait that declares the constant it gets from another trait with the same value, and a parent class constant with the same value.
trait BaseD {
    public const D = 1;
}

trait LeftD {
    use BaseD;
}

trait RightD {
    use BaseD;
}

trait RedeclaringD {
    use BaseD;
    public const D = 1;
}

class ClassDiamondD {
    use LeftD, RightD;
}

class ClassOuterAndInnerD {
    use LeftD, BaseD;
}

class ClassRedeclaringD {
    use RedeclaringD, BaseD;
}

class ParentSameD {
    public const D = 1;
}

class ChildSameD extends ParentSameD {
    use LeftD;
}
echo ClassDiamondD::D, ClassOuterAndInnerD::D, ClassRedeclaringD::D, ParentSameD::D, ChildSameD::D;

// 5. PHP compares the values of the constants, not where they are written: PHP 8.2 to 8.5 accept ClassValuesOnLines.
trait ValuesFirst {
    public const E = [1, 2]; public const F = null; public const G = 1 + 1; public const H = -1; public const I = ['k' => [true, 0.5]];
}

trait ValuesSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const E = [1, 2];
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const F = null;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const G = 1 + 1;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const H = -1;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const I = ['k' => [TRUE, 0.5]];
}

class ClassValuesOnLines {
    use ValuesFirst, ValuesSecond;
}
var_dump(ClassValuesOnLines::E, ClassValuesOnLines::F, ClassValuesOnLines::G, ClassValuesOnLines::H, ClassValuesOnLines::I);

// 6. PHP evaluates the values before it compares them: PHP 8.2 to 8.5 accept ClassEvaluated, ClassEvaluatedOwn and ChildEvaluated.
// `self` in the value of a constant of a trait is the class using the trait.
class OtherJ {
    public const X = 5;
}

trait EvaluatedFirst {
    public const J = OtherJ::X;
    public const K = 'a' . 'b';
    /** @phan-suppress-next-line PhanUndeclaredConstantOfClass */
    public const L = [...self::LIST, 3];
    public const M = ['1' => 'x'];
    /** @phan-suppress-next-line PhanUndeclaredConstantOfClass */
    public const N = self::OWN;
}

trait EvaluatedSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const J = 5;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const K = 'ab';
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const L = [1, 2, 3];
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const M = [1 => 'x'];
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const N = 2;
}

class ClassEvaluated {
    use EvaluatedFirst, EvaluatedSecond;
    public const LIST = [1, 2];
    public const OWN = 1 + 1;
}
var_dump(ClassEvaluated::J, ClassEvaluated::K, ClassEvaluated::L, ClassEvaluated::M, ClassEvaluated::N, ClassEvaluated::LIST, ClassEvaluated::OWN);

trait TraitTwoO {
    public const O = 2;
}

class ClassEvaluatedOwn {
    use TraitTwoO;
    public const ONE = 1;
    public const O = self::ONE + 1;
}
echo ClassEvaluatedOwn::O;

class ParentEvaluatedP {
    public const P = 2;
}
echo ParentEvaluatedP::P;

trait TraitEvaluatedP {
    public const P = 1 + 1;
}

class ChildEvaluatedP extends ParentEvaluatedP {
    use TraitEvaluatedP;
}
echo ChildEvaluatedP::P;

// 7. PHP 8.2 to 8.5 reject each of these classes: the values are not identical (`===`).
// "OrderFirst and OrderSecond define the same constant (Q) in the composition of ClassOrder", and so on.
trait OrderFirst {
    public const Q = [1, 2];
}

trait OrderSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const Q = [1 => 2, 0 => 1];
}

class ClassOrder {
    use OrderFirst, OrderSecond;
}
var_dump(ClassOrder::Q);

trait IntR {
    public const R = 1;
}

trait FloatR {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const R = 1.0;
}

class ClassIntFloat {
    use IntR, FloatR;
}
var_dump(ClassIntFloat::R);

enum Suit: string {
    case Hearts = 'H';
    case Spades = 'S';
}

trait CaseHeartsS {
    public const S = Suit::Hearts;
}

trait CaseSpadesS {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const S = Suit::Spades;
}

class ClassTwoCases {
    use CaseHeartsS, CaseSpadesS;
}
var_dump(ClassTwoCases::S);

enum Letter: string {
    case A = 'a';
}

trait CaseT {
    public const T = Letter::A;
}

trait BackingValueT {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const T = 'a';
}

class ClassCaseAndBackingValue {
    use CaseT, BackingValueT;
}
var_dump(ClassCaseAndBackingValue::T);

class OtherU {
    public const X = 5;
}

trait ReferenceU {
    public const U = OtherU::X;
}

trait LiteralU {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const U = 6;
}

class ClassReferenceAndLiteral {
    use ReferenceU, LiteralU;
}
var_dump(ClassReferenceAndLiteral::U);

trait LineFirstV {
    public const V = __LINE__;
}

trait LineSecondV {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const V = __LINE__;
}

class ClassLines {
    use LineFirstV, LineSecondV;
}
var_dump(ClassLines::V);

trait SumW {
    public const W = 0.1 + 0.2;
}

trait LiteralW {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const W = 0.3;
}

class ClassFloatSum {
    use SumW, LiteralW;
}
var_dump(ClassFloatSum::W);

// 8. `self` in the value of a constant of a trait is the class using the trait.
// PHP 8.2 to 8.5: "SelfFirstX and SelfSecondX define the same constant (X) in the composition of ClassSelfX".
trait SelfFirstX {
    /** @phan-suppress-next-line PhanUndeclaredConstantOfClass */
    public const X = self::OWN_X;
}

trait SelfSecondX {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const X = 2;
}

class ClassSelfX {
    use SelfFirstX, SelfSecondX;
    public const OWN_X = 1;
}
var_dump(ClassSelfX::X, ClassSelfX::OWN_X);

// 9. PHP 8.2+ throws "Cannot access trait constant SourceY::A directly" when it evaluates the value of the constant of FirstY,
// and rejects ClassTraitAccessY: "FirstY and SecondY define the same constant (Y) in the composition of ClassTraitAccessY".
// In a trait that uses other traits, `self` is that trait: when PHP composes OuterSelfZ, it throws
// "Cannot access trait constant self::A directly" and rejects it: "FirstZ and SecondZ define the same constant (Z) in the composition of OuterSelfZ".
trait SourceY {
    public const A = 1;
}

trait FirstY {
    public const Y = SourceY::A;
}

trait SecondY {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const Y = 1;
}

class ClassTraitAccessY {
    use FirstY, SecondY;
}
var_dump(ClassTraitAccessY::Y);

trait FirstZ {
    public const A = 1;
    public const Z = self::A;
}

trait SecondZ {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const Z = 1;
}

trait OuterSelfZ {
    use FirstZ, SecondZ;
}

class ClassOuterSelfZ {
    use OuterSelfZ;
}
var_dump(ClassOuterSelfZ::Z);

// 10. Phan does not evaluate global constants other than `null`, `true` and `false`, magic constants other than `__LINE__`,
// `X::class`, and some operators such as `%`. These are not reported whether or not the values are the same.
// PHP 8.2 to 8.5 accept ClassUnknownSame, and reject ClassUnknownDifferent: "UnknownFirst and UnknownSecond define the same constant (GLOBAL_DIFFERENT) ...".
const GLOBAL_ONE = 1;

trait UnknownFirst {
    public const INT_SIZE = PHP_INT_SIZE;
    public const NAME = __CLASS__;
    public const MOD = 7 % 4;
}

trait UnknownSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const INT_SIZE = 8;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NAME = __CLASS__;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const MOD = 3;
}

class ClassUnknownSame {
    use UnknownFirst, UnknownSecond;
}
var_dump(ClassUnknownSame::INT_SIZE, ClassUnknownSame::NAME, ClassUnknownSame::MOD);

trait GlobalFirst {
    public const GLOBAL_DIFFERENT = GLOBAL_ONE;
}

trait GlobalSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const GLOBAL_DIFFERENT = 2;
}

class ClassUnknownDifferent {
    use GlobalFirst, GlobalSecond;
}
var_dump(ClassUnknownDifferent::GLOBAL_DIFFERENT);
