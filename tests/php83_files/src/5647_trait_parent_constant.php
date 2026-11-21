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

// 14. `self` in a trait constant binds to the composing class before checking composition compatibility.
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

// 15. `static` does not bind to the composing class name and remains incompatible with it.
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

// 16. Nested trait composition defers `self` resolution until a concrete class uses the trait.
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
