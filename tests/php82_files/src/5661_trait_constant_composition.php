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
// and some expressions such as `?:` and `[3][0]`. These are not reported whether or not the values are the same.
// PHP 8.2 to 8.5 accept ClassUnknownSame, and reject ClassUnknownDifferent: "UnknownFirst and UnknownSecond define the same constant (GLOBAL_DIFFERENT) ...".
const GLOBAL_ONE = 1;

trait UnknownFirst {
    public const INT_SIZE = PHP_INT_SIZE;
    public const NAME = __CLASS__;
    public const DIM = [3][0];
}

trait UnknownSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const INT_SIZE = 8;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NAME = __CLASS__;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const DIM = 3;
}

class ClassUnknownSame {
    use UnknownFirst, UnknownSecond;
}
var_dump(ClassUnknownSame::INT_SIZE, ClassUnknownSame::NAME, ClassUnknownSame::DIM);

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

// 11. `X::class` is the name of the class resolved with the namespace and the `use` statements, as it is written: PHP 8.2 to 8.5 reject
// ClassTwoModels ("ModelFoo and ModelBar define the same constant (MODEL) in the composition of ClassTwoModels")
// and ClassTwoSpellings ("ModelSpellingFirst and ModelSpellingSecond ...": `modelfoo::class` and `ModelFoo::class` are different strings).
class ModelFoo {}
class ModelBar {}

trait ModelFooTrait {
    public const MODEL = ModelFoo::class;
}

trait ModelBarTrait {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const MODEL = ModelBar::class;
}

class ClassTwoModels {
    use ModelFooTrait, ModelBarTrait;
}
var_dump(ClassTwoModels::MODEL, new ModelFoo(), new ModelBar());

trait ModelSpellingFirst {
    public const SPELLING = modelfoo::class;
}

trait ModelSpellingSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SPELLING = ModelFoo::class;
}

class ClassTwoSpellings {
    use ModelSpellingFirst, ModelSpellingSecond;
}
var_dump(ClassTwoSpellings::SPELLING);

// 12. PHP 8.2 to 8.5 accept ClassSameModel: the same class name written in different ways, and `self::class` in a trait,
// which is the name of the class using the trait.
use Issue5661Php82 as AliasedNamespace;

trait SameModelFirst {
    public const NAME_ONE = ModelFoo::class;
    public const NAME_TWO = AliasedNamespace\ModelFoo::class;
    public const NAME_THREE = self::class;
}

trait SameModelSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NAME_ONE = \Issue5661Php82\ModelFoo::class;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NAME_TWO = namespace\ModelFoo::class;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NAME_THREE = 'Issue5661Php82\ClassSameModel';
}

class ClassSameModel {
    use SameModelFirst, SameModelSecond;
}
var_dump(ClassSameModel::NAME_ONE, ClassSameModel::NAME_TWO, ClassSameModel::NAME_THREE);

// 13. PHP 8.2 to 8.5 reject ClassSelfClassAndTraitName: `self::class` in SelfClassTrait is the name of the class using it, not of the trait
// ("SelfClassTrait and TraitNameTrait define the same constant (SELF_NAME) in the composition of ClassSelfClassAndTraitName").
trait SelfClassTrait {
    public const SELF_NAME = self::class;
}

trait TraitNameTrait {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SELF_NAME = 'Issue5661Php82\SelfClassTrait';
}

class ClassSelfClassAndTraitName {
    use SelfClassTrait, TraitNameTrait;
}
var_dump(ClassSelfClassAndTraitName::SELF_NAME);

// 14. The integer operators `%`, `|`, `&`, `^`, `<<`, `>>` and `~` are evaluated for integers.
// PHP 8.2 to 8.5 accept ClassSameFlags, and reject ClassTwoFlags, ClassTwoShifts and ClassTwoModulos
// ("FlagsFirst and FlagsSecond define the same constant (FLAGS) in the composition of ClassTwoFlags", and so on).
trait SameFlagsFirst {
    public const FLAG_A = 1;
    public const FLAG_B = 2;
    public const FLAGS = self::FLAG_A | self::FLAG_B;
    public const MASK = 6 & 3;
    public const TOGGLE = 6 ^ 3;
    public const SHIFTED = (1 << 4) >> 1;
    public const MODULO = -7 % 3;
    public const NONE = ~0;
}

trait SameFlagsSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const FLAGS = 3;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const MASK = 2;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const TOGGLE = 5;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SHIFTED = 8;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const MODULO = -1;
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NONE = -1;
}

class ClassSameFlags {
    use SameFlagsFirst, SameFlagsSecond;
}
var_dump(ClassSameFlags::FLAG_A, ClassSameFlags::FLAG_B, ClassSameFlags::FLAGS, ClassSameFlags::MASK, ClassSameFlags::TOGGLE, ClassSameFlags::SHIFTED, ClassSameFlags::MODULO, ClassSameFlags::NONE);

trait FlagsFirst {
    public const READ = 1;
    public const WRITE = 2;
    public const FLAGS = self::READ | self::WRITE;
}

trait FlagsSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const FLAGS = 4;
}

class ClassTwoFlags {
    use FlagsFirst, FlagsSecond;
}
var_dump(ClassTwoFlags::READ, ClassTwoFlags::WRITE, ClassTwoFlags::FLAGS);

trait ShiftFirst {
    public const SHIFT = 1 << 3;
}

trait ShiftSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SHIFT = 9;
}

class ClassTwoShifts {
    use ShiftFirst, ShiftSecond;
}
var_dump(ClassTwoShifts::SHIFT);

trait ModuloFirst {
    public const MODULO = 7 % 4;
}

trait ModuloSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const MODULO = 2;
}

class ClassTwoModulos {
    use ModuloFirst, ModuloSecond;
}
var_dump(ClassTwoModulos::MODULO);

// 15. These operators are not evaluated for operands that are not integers. PHP 8.2 to 8.5 reject ClassFloatOperand
// ("FloatOperandFirst and FloatOperandSecond define the same constant (FLOAT_FLAGS) ..."): `1 | 2.0` is 3. Phan does not report it.
trait FloatOperandFirst {
    public const FLOAT_FLAGS = 1 | 2.0;
}

trait FloatOperandSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const FLOAT_FLAGS = 4;
}

class ClassFloatOperand {
    use FloatOperandFirst, FloatOperandSecond;
}
var_dump(ClassFloatOperand::FLOAT_FLAGS);

// 16. PHP does not evaluate the right operand of `&&`, `||` and `??` when the left operand determines the result, so it does not throw
// "Cannot access trait constant SourceShortCircuit::X directly" here: PHP 8.2 to 8.5 accept ClassShortCircuit.
// Phan does not know the value of the global constant, and does not evaluate the right operand.
const SHORT_CIRCUIT_OFF = false;

trait SourceShortCircuit {
    public const X = 1;
}

trait ShortCircuitFirst {
    /** @phan-suppress-next-line PhanImpossibleCondition */
    public const SHORT = SHORT_CIRCUIT_OFF && SourceShortCircuit::X;
}

trait ShortCircuitSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SHORT = false;
}

class ClassShortCircuit {
    use ShortCircuitFirst, ShortCircuitSecond;
}
var_dump(ClassShortCircuit::SHORT);

// 17. The constant SELF_NAME_P that ChildSelfName inherits from ParentSelfName has the value `self::class` of SelfNameTrait. When PHP has not
// evaluated it before, it evaluates it in the scope of ChildSelfName and rejects ChildSelfName (PHP 8.2 to 8.4:
// "ParentSelfName and OtherNameTrait define the same constant (SELF_NAME_P) ..."). When `ParentSelfName::SELF_NAME_P` was evaluated before
// ChildSelfName is declared, it is 'Issue5661Php82\ParentSelfName' and PHP accepts ChildSelfName. Phan does not know which, and does not report it.
trait SelfNameTrait {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SELF_NAME_P = self::class;
}

class ParentSelfName {
    use SelfNameTrait;
}

trait OtherNameTrait {
    public const SELF_NAME_P = 'Issue5661Php82\ParentSelfName';
}

class ChildSelfName extends ParentSelfName {
    use OtherNameTrait;
}
var_dump(ChildSelfName::SELF_NAME_P);

// 18. PHP evaluates a constant that it fetches in the scope of the class that the constant is bound to, whether or not it evaluated it before:
// `ChildBoundName::BOUND_NAME` is 'Issue5661Php82\ParentBoundName' (the class using BoundNameTrait). PHP 8.2 to 8.5 reject ClassBoundName
// ("BoundNameReference and ChildNameLiteral define the same constant (REFERENCED_NAME) in the composition of ClassBoundName").
trait BoundNameTrait {
    public const BOUND_NAME = self::class;
}

class ParentBoundName {
    use BoundNameTrait;
}

class ChildBoundName extends ParentBoundName {
}

trait BoundNameReference {
    public const REFERENCED_NAME = ChildBoundName::BOUND_NAME;
}

trait ChildNameLiteral {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const REFERENCED_NAME = 'Issue5661Php82\ChildBoundName';
}

class ClassBoundName {
    use BoundNameReference, ChildNameLiteral;
}
var_dump(ClassBoundName::REFERENCED_NAME);

// 19. `AliasedStdClass::class` is 'stdclass', as written in the `use` statement, and PHP 8.2 to 8.5 accept ClassAliasComparison.
// Phan knows the class as `stdClass`, and does not evaluate an operator applied to a class name whose letter case it does not know.
use stdclass as AliasedStdClass;

trait AliasComparisonFirst {
    /** @phan-suppress-next-line PhanImpossibleValueComparison */
    public const SAME_NAME = AliasedStdClass::class === 'stdclass';
}

trait AliasComparisonSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SAME_NAME = true;
}

class ClassAliasComparison {
    use AliasComparisonFirst, AliasComparisonSecond;
}
var_dump(ClassAliasComparison::SAME_NAME);

// 20. PHP throws "Cannot add element to the array as the next element is already occupied" when it evaluates OVERFLOW of OverflowFirst
// (the element without a key follows the key PHP_INT_MAX), and rejects ClassOverflow (PHP 8.2 to 8.5:
// "OverflowFirst and OverflowSecond define the same constant (OVERFLOW) in the composition of ClassOverflow").
// Phan does not know the value and does not report it.
trait OverflowFirst {
    /** @phan-suppress-next-line PhanPluginMixedKeyNoKey */
    public const OVERFLOW = [(1 << 62) + ((1 << 62) - 1) => 1, 2];
}

trait OverflowSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const OVERFLOW = [1];
}

class ClassOverflow {
    use OverflowFirst, OverflowSecond;
}
var_dump(ClassOverflow::OVERFLOW);

// 21. A trait whose PHPDoc has `@extends` or `@inherits` does not get the constants of that class in PHP: PHP 8.2 to 8.5 accept
// ChildOfAnnotatedBase, which declares its own C_ANNOTATED.
class AnnotatedBase {
    public const C_ANNOTATED = 1;
}

/** @extends AnnotatedBase */
trait AnnotatedTrait {
}

class ChildOfAnnotatedBase extends AnnotatedBase {
    use AnnotatedTrait;
    public const C_ANNOTATED = 2;
}
var_dump(AnnotatedBase::C_ANNOTATED, ChildOfAnnotatedBase::C_ANNOTATED);

// 22. In an array in a constant expression, PHP 8.1 to 8.5 give an element without a key the largest integer key so far plus one
// (0 if there is none), also after negative keys and for unpacked elements, and a numeric string key is an integer key:
// PHP 8.2 to 8.5 accept ClassArrayKeys, and reject ClassArrayKeysDiffer
// ("ArrayKeysFirst and ArrayKeysOther define the same constant (KEYS_D) in the composition of ClassArrayKeysDiffer").
trait ArrayKeysFirst {
    /** @phan-suppress-next-line PhanPluginMixedKeyNoKey */
    public const KEYS_A = [-5 => 1, 2];
    /** @phan-suppress-next-line PhanPluginMixedKeyNoKey */
    public const KEYS_B = [-3 => 'a', ...[1, 2], 'b'];
    /** @phan-suppress-next-line PhanPluginMixedKeyNoKey */
    public const KEYS_C = ['5' => 'a', 'b', ...[-7 => 'c']];
    /** @phan-suppress-next-line PhanPluginMixedKeyNoKey */
    public const KEYS_D = [-2 => 'a', 'b'];
}

trait ArrayKeysSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const KEYS_A = [-5 => 1, -4 => 2];
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const KEYS_B = [-3 => 'a', -2 => 1, -1 => 2, 0 => 'b'];
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const KEYS_C = [5 => 'a', 6 => 'b', 7 => 'c'];
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const KEYS_D = [-2 => 'a', -1 => 'b'];
}

class ClassArrayKeys {
    use ArrayKeysFirst, ArrayKeysSecond;
}
var_dump(ClassArrayKeys::KEYS_A, ClassArrayKeys::KEYS_B, ClassArrayKeys::KEYS_C, ClassArrayKeys::KEYS_D);

trait ArrayKeysOther {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const KEYS_D = [-2 => 'a', 0 => 'b'];
}

class ClassArrayKeysDiffer {
    use ArrayKeysFirst, ArrayKeysOther;
}
var_dump(ClassArrayKeysDiffer::KEYS_D);

// 23. `true` and `\true` are the literal: PHP 8.2 to 8.5 reject ClassFullyQualifiedTrue
// ("FullyQualifiedTrueFirst and FullyQualifiedTrueSecond define the same constant (FQ_TRUE) in the composition of ClassFullyQualifiedTrue").
// `namespace\true` is the constant Issue5661Php82\true, which can be defined with define(). It is not defined here, so PHP fails to evaluate
// NS_TRUE and rejects ClassNamespaceTrue; with `define('Issue5661Php82\true', false)` before it, PHP accepts it. Phan does not report it.
trait FullyQualifiedTrueFirst {
    public const FQ_TRUE = \true;
}

trait FullyQualifiedTrueSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const FQ_TRUE = false;
}

class ClassFullyQualifiedTrue {
    use FullyQualifiedTrueFirst, FullyQualifiedTrueSecond;
}
var_dump(ClassFullyQualifiedTrue::FQ_TRUE);

trait NamespaceTrueFirst {
    /** @phan-suppress-next-line PhanUndeclaredConstant */
    public const NS_TRUE = namespace\true;
}

trait NamespaceTrueSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NS_TRUE = false;
}

class ClassNamespaceTrue {
    use NamespaceTrueFirst, NamespaceTrueSecond;
}
var_dump(ClassNamespaceTrue::NS_TRUE);

// 24. Phan stops evaluating the values of two constants after 3000 nodes and does not report them then. A constant that is referenced
// several times is evaluated each time: BUDGET_4 has 10000 references. PHP 8.2 to 8.5 reject ClassEvaluationBudget
// ("BudgetFirst and BudgetSecond define the same constant (BUDGET) in the composition of ClassEvaluationBudget": 10000 and 1).
class BudgetValues {
    public const BUDGET_0 = 1;
    public const BUDGET_1 = self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0 + self::BUDGET_0;
    public const BUDGET_2 = self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1 + self::BUDGET_1;
    public const BUDGET_3 = self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2 + self::BUDGET_2;
    public const BUDGET_4 = self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3 + self::BUDGET_3;
}

trait BudgetFirst {
    public const BUDGET = BudgetValues::BUDGET_4;
}

trait BudgetSecond {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const BUDGET = 1;
}

class ClassEvaluationBudget {
    use BudgetFirst, BudgetSecond;
}
var_dump(BudgetValues::BUDGET_0, BudgetValues::BUDGET_1, BudgetValues::BUDGET_2, BudgetValues::BUDGET_3, ClassEvaluationBudget::BUDGET);

// 25. A trait whose PHPDoc has `@extends` does not get the constants of that class in PHP, also not the constants that the class gets
// from a trait: PHP 8.2 to 8.5 accept ChildOfParentWithTrait. They reject ChildOfParentWithTraitUsed, whose trait also uses ParentTraitConstant
// ("ChildOfParentWithTraitUsed and AnnotatedTraitUsing define the same constant (X_FROM_TRAIT) in the composition of ChildOfParentWithTraitUsed").
trait ParentTraitConstant {
    public const X_FROM_TRAIT = 1;
}

class ParentWithTrait {
    use ParentTraitConstant;
}
echo ParentWithTrait::X_FROM_TRAIT;

/** @extends ParentWithTrait */
trait AnnotatedTraitOnly {
}

class ChildOfParentWithTrait extends ParentWithTrait {
    use AnnotatedTraitOnly;
    public const X_FROM_TRAIT = 2;
}
echo ChildOfParentWithTrait::X_FROM_TRAIT;

/** @extends ParentWithTrait */
trait AnnotatedTraitUsing {
    use ParentTraitConstant;
}

class ChildOfParentWithTraitUsed extends ParentWithTrait {
    use AnnotatedTraitUsing;
    public const X_FROM_TRAIT = 2;
}
echo ChildOfParentWithTraitUsed::X_FROM_TRAIT;
