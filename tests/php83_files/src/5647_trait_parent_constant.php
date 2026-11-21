<?php

namespace Issue5647Php83;

// Typed constants of traits and parent classes before PHP 8.5, where a trait constant does not override a parent class constant.

// 1. A typed trait constant that conflicts with a typed parent constant reports only PhanIncompatibleCompositionConstant.
class ParentTypedInt {
    public const int C = 0;
}
echo ParentTypedInt::C;

trait TraitTypedString {
    public const string C = 'x';
}

class ChildConflict extends ParentTypedInt {
    use TraitTypedString;
}
echo ChildConflict::C;

// 2. An untyped trait constant with the same visibility and value as a typed parent constant.
// PHP 8.3 and 8.4 reject it as an incompatible composition because the declared types differ.
trait TraitUntypedSameValue {
    public const C = 0;
}

class ChildUntypedSameValue extends ParentTypedInt {
    use TraitUntypedSameValue;
}
echo ChildUntypedSameValue::C;

// 3. Typed constants of two traits that conflict report only PhanIncompatibleCompositionConstant.
trait TraitTypedInt {
    public const int D = 1;
}

trait TraitTypedOther {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const string D = 'x';
}

class ChildTwoTypedTraits {
    use TraitTypedInt, TraitTypedOther;
}
echo ChildTwoTypedTraits::D;

// 4. An untyped constant of a class cannot override a typed constant of its parent class.
class ParentClassTyped {
    public const int E = 0;
    protected const int F = 0;
    public static function getF(): int {
        return self::F;
    }
}
echo ParentClassTyped::E, ParentClassTyped::getF();

class ChildClassUntyped extends ParentClassTyped {
    public const E = 1;
    protected const F = 1;
}
echo ChildClassUntyped::E;

// 5. An untyped constant of an interface cannot override a typed constant of an interface it extends.
interface ParentInterfaceTyped {
    const int G = 0;
}
echo ParentInterfaceTyped::G;

interface ChildInterfaceUntyped extends ParentInterfaceTyped {
    const G = 1;
}
echo ChildInterfaceUntyped::G;

// 6. An untyped constant of a class cannot override a typed constant of an interface the class implements.
class ImplementsUntyped implements ParentInterfaceTyped {
    const G = 2;
}
echo ImplementsUntyped::G;

// 7. An untyped constant can override an untyped constant or a private typed constant.
class ParentUntypedOrPrivate {
    public const H = 0;
    private const int I = 0;
    public static function getI(): int {
        return self::I;
    }
}
echo ParentUntypedOrPrivate::H, ParentUntypedOrPrivate::getI();

class ChildUntypedAllowed extends ParentUntypedOrPrivate {
    public const H = 1;
    public const I = 'x';
}
echo ChildUntypedAllowed::H, ChildUntypedAllowed::I;

// 8. `self` in the type of a trait constant is bound to the class using the trait, and subclasses inherit the constant
// with that binding: a constant declared in a subclass can override it with the type of the class using the trait (PHP accepts this).
trait TraitSelfBound {
    public const ?self J = null;
}

class SelfBoundBase {
    use TraitSelfBound;
}
var_dump(SelfBoundBase::J);

class SelfBoundMiddle extends SelfBoundBase {}

class SelfBoundChildClass extends SelfBoundMiddle {
    public const ?SelfBoundBase J = null;
}
var_dump(SelfBoundChildClass::J);

// 9. An untyped constant can override DateTime::ATOM or ArrayObject::STD_PROP_LIST in PHP 8.3,
// where they have no type (they got types in PHP 8.4; bundled SPL stubs declare 8.4+ types).
// Neither is reported for this target version, regardless of the PHP version running Phan.
class ChildOfDateTime extends \DateTime {
    public const ATOM = 'custom';
}
var_dump(ChildOfDateTime::ATOM);

class ChildOfArrayObject extends \ArrayObject {
    public const STD_PROP_LIST = 1;
}
var_dump(ChildOfArrayObject::STD_PROP_LIST);

// 10. A trait constant must remain covariant with an interface constant, even when their values differ.
// Interfaces are not checked by the trait/parent class composition conflict check.
interface InterfaceTypedInt {
    public const int K = 0;
}
echo InterfaceTypedInt::K;

trait TraitTypedStringK {
    public const string K = 'x';
}

class ImplementsTypedInterfaceWithTrait implements InterfaceTypedInt {
    use TraitTypedStringK;
}
echo ImplementsTypedInterfaceWithTrait::K;

// A compatible trait constant overriding the interface constant must not report a mismatch.
trait TraitTypedIntK {
    public const int K = 1;
}

class ImplementsCompatibleInterfaceWithTrait implements InterfaceTypedInt {
    use TraitTypedIntK;
}
echo ImplementsCompatibleInterfaceWithTrait::K;

// 11. A class narrowing the declared type of a trait constant with the same value still fails composition,
// even when the class also extends a parent declaring the same constant.
class ParentUnionL {
    public const int|string L = 0;
}
echo ParentUnionL::L;

trait TraitUnionL {
    public const int|string L = 1;
}

class ChildNarrowedCompositionL extends ParentUnionL {
    use TraitUnionL;
    public const int L = 1;
}
echo ChildNarrowedCompositionL::L;

// 12. Identical union types in different order with the same visibility and value remain compatible.
class ChildCompatibleUnionOrderL extends ParentUnionL {
    use TraitUnionL;
    public const string|int L = 1;
}
echo ChildCompatibleUnionOrderL::L;

// 13. Equivalent iterable alias (iterable === array|\Traversable) and nullable spellings (?int === int|null) are compatible.
trait TraitIterableM {
    public const iterable|int M = 0;
}
class ClassIterableM {
    use TraitIterableM;
    public const array|\Traversable|int M = 0;
}
var_dump(ClassIterableM::M);

trait TraitNullableN {
    public const ?int N = 0;
}
class ClassNullableN {
    use TraitNullableN;
    public const int|null N = 0;
}
var_dump(ClassNullableN::N);

// 14. PHP resolves `self` in the type of the constant that ClassSelfO already has (the one of TraitSelfO, which is used first)
// against ClassSelfO, so it accepts the constant of TraitExplicitO. With the traits in the other order, PHP resolves `self`
// against TraitSelfO and rejects ClassSelfO. Phan does not compare declared types that contain `self` and reports neither.
trait TraitSelfO {
    public const self|int O = 0;
}
trait TraitExplicitO {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const ClassSelfO|int O = 0;
}
class ClassSelfO {
    use TraitSelfO, TraitExplicitO;
}
var_dump(ClassSelfO::O);

// 15. PHP does not resolve `static` in this check: it rejects `static|int` and `ClassStaticP|int` in ClassStaticP
// ("TraitStaticP and TraitExplicitP define the same constant (P) in the composition of ClassStaticP").
trait TraitStaticP {
    public const static|int P = 0;
}
trait TraitExplicitP {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const ClassStaticP|int P = 0;
}
class ClassStaticP {
    use TraitStaticP, TraitExplicitP;
}
var_dump(ClassStaticP::P);

// 16. PHP checks the constants of TraitNestedSelfQ and TraitNestedExplicitQ when it composes TraitNestedCombinedQ, with `self`
// resolved against TraitNestedCombinedQ. ClassNestedQ is not loaded yet at that point, so the check passes, and the classes
// using TraitNestedCombinedQ do not check these constants again (PHP accepts both classes).
trait TraitNestedSelfQ {
    public const self|int Q = 0;
}
trait TraitNestedExplicitQ {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const ClassNestedQ|int Q = 0;
}
trait TraitNestedCombinedQ {
    use TraitNestedSelfQ, TraitNestedExplicitQ;
}
class ClassNestedQ {
    use TraitNestedCombinedQ;
}
var_dump(ClassNestedQ::Q);

class ClassNestedQSecond {
    use TraitNestedCombinedQ;
}
var_dump(ClassNestedQSecond::Q);

// 17. PHP checks that each of the two declared types is a subtype of the other, so `RedundantBaseR|RedundantSubR|int`
// and `RedundantBaseR|int` are compatible (PHP accepts this).
class RedundantBaseR {}
class RedundantSubR extends RedundantBaseR {}
trait TraitRedundantR {
    public const RedundantBaseR|RedundantSubR|int R = 0;
}
class ClassRedundantR {
    use TraitRedundantR;
    public const RedundantBaseR|int R = 0;
}
var_dump(ClassRedundantR::R, new RedundantSubR());

// 18. PHP resolves `self` in the type of the constant of TraitNestedSelfS against TraitNestedOuterS when it composes
// TraitNestedOuterS, which gives the declared type of the constant of TraitNestedOtherS (PHP accepts this).
trait TraitNestedSelfS {
    public const self|int S = 0;
}
trait TraitNestedOtherS {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const TraitNestedOuterS|int S = 0;
}
trait TraitNestedOuterS {
    use TraitNestedSelfS, TraitNestedOtherS;
}
class ClassNestedS {
    use TraitNestedOuterS;
}
var_dump(ClassNestedS::S);

// 19. Before PHP 8.5, PHP checks the constant of TraitChildNameT against the constant that ChildSelfT inherits from ParentSelfT,
// with `self` resolved against ChildSelfT (PHP 8.3, 8.4 and 8.5 accept this).
class ParentSelfT {
    public const self|int T = 0;
}
echo ParentSelfT::T;

trait TraitChildNameT {
    public const ChildSelfT|int T = 0;
}

class ChildSelfT extends ParentSelfT {
    use TraitChildNameT;
}
var_dump(ChildSelfT::T);

// 20. PHP resolves `self` in the type of the constant that ClassTwoSelfU already has (the one of TraitSelfFirstU) against
// ClassTwoSelfU, and `self` in the type of the constant of TraitSelfSecondU against TraitSelfSecondU, and rejects ClassTwoSelfU
// ("TraitSelfFirstU and TraitSelfSecondU define the same constant (U) in the composition of ClassTwoSelfU").
// Phan does not compare declared types that contain `self`, because the result of PHP's check depends on the classes
// that are loaded when it is done, and does not report this.
trait TraitSelfFirstU {
    public const self|int U = 0;
}
trait TraitSelfSecondU {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const self|int U = 0;
}
class ClassTwoSelfU {
    use TraitSelfFirstU, TraitSelfSecondU;
}
var_dump(ClassTwoSelfU::U);

// 21. The constant of a class is the constant of the trait it uses, not the constant of an interface it implements:
// PHP 8.3, 8.4 and 8.5 give 'trait' for ImplementsInterfaceWithTraitV::V, so getTraitValueOfV() returns a string.
interface InterfaceUntypedV {
    const V = 0;
}
echo InterfaceUntypedV::V;

trait TraitUntypedV {
    public const V = 'trait';
}

class ImplementsInterfaceWithTraitV implements InterfaceUntypedV {
    use TraitUntypedV;
}

function getTraitValueOfV(): int {
    return ImplementsInterfaceWithTraitV::V;
}
echo getTraitValueOfV();

// 22. A trait constant cannot override a final constant of an interface, even with the same value
// (PHP 8.3, 8.4 and 8.5: "ImplementsFinalInterfaceWithTrait::W cannot override final constant InterfaceFinalW::W").
interface InterfaceFinalW {
    final public const int W = 0;
}
echo InterfaceFinalW::W;

trait TraitTypedW {
    public const int W = 0;
}

class ImplementsFinalInterfaceWithTrait implements InterfaceFinalW {
    use TraitTypedW;
}
echo ImplementsFinalInterfaceWithTrait::W;

// 23. The constants of an interface are public: a protected or private trait constant cannot override one
// (PHP 8.3, 8.4 and 8.5: "Access level to ImplementsInterfaceWithNonPublicTrait::X must be public (as in interface InterfacePublicXY)").
interface InterfacePublicXY {
    public const int X = 0;
    public const int Y = 0;
}
echo InterfacePublicXY::X, InterfacePublicXY::Y;

trait TraitNonPublicXY {
    protected const int X = 0;
    private const int Y = 0;

    public static function getXY(): int {
        return self::X + self::Y;
    }
}

class ImplementsInterfaceWithNonPublicTrait implements InterfacePublicXY {
    use TraitNonPublicXY;
}
echo ImplementsInterfaceWithNonPublicTrait::getXY();

// 24. A final trait constant can override a constant of an interface that is not final (PHP 8.3, 8.4 and 8.5 accept this).
interface InterfaceNonFinalZ {
    public const int Z = 0;
}
echo InterfaceNonFinalZ::Z;

trait TraitFinalZ {
    final public const int Z = 1;
}

class ImplementsInterfaceWithFinalTrait implements InterfaceNonFinalZ {
    use TraitFinalZ;
}
echo ImplementsInterfaceWithFinalTrait::Z;
