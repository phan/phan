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
// PHP 8.3 and 8.4 reject it because the types differ. The composition check of Phan compares only
// the visibility and the value, so PhanConstantTypeMismatchInheritance reports it.
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

// 9. An untyped constant can override DateTime::ATOM in PHP 8.3, where it has no type (it has one since PHP 8.4).
// It is not reported for this target version, also when the PHP version running Phan is newer.
class ChildOfDateTime extends \DateTime {
    public const ATOM = 'custom';
}
var_dump(ChildOfDateTime::ATOM);
