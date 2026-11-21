<?php

namespace Issue5647Php85;

// 1. Private parent constant does not conflict with trait constant.
class ParentPrivate {
    private const C = 0;
    public static function getParent(): int {
        return self::C;
    }
}
echo ParentPrivate::getParent();

trait TraitPublic {
    public const C = 1;
}

class ChildPrivate extends ParentPrivate {
    use TraitPublic;
}
echo ChildPrivate::C;

// 2. Non-private parent constant is overridden by the trait constant in PHP 8.5+.
class ParentPublic {
    public const C = 0;
}
echo ParentPublic::C;

class ChildPublicOverride extends ParentPublic {
    use TraitPublic;
}
echo ChildPublicOverride::C;

// 3. Widening visibility from protected parent to public trait is allowed in PHP 8.5+.
class ParentProtected {
    protected const C = 0;
    public static function getParent(): int {
        return self::C;
    }
}
echo ParentProtected::getParent();

class ChildWidened extends ParentProtected {
    use TraitPublic;
}
echo ChildWidened::C;

// 4. Narrowing visibility still reports PhanConstantAccessSignatureMismatch (no composition error).
// It is reported at the class using the trait, not at the constant of the trait, so that it is also reported
// when the file of the trait is excluded from analysis.
trait TraitProtected {
    protected const C = 1;
}

class ChildNarrowed extends ParentPublic {
    use TraitProtected;
    public static function get(): int {
        return self::C;
    }
}
echo ChildNarrowed::get();

// 5. Overriding a final parent constant still reports PhanAccessOverridesFinalConstant (no composition error).
class ParentFinal {
    final public const C = 0;
}
echo ParentFinal::C;

class ChildFinal extends ParentFinal {
    use TraitPublic;
}
echo ChildFinal::C;

// 6. Conflicting constant in the using class itself still reports PhanIncompatibleCompositionConstant.
class ChildOwnConflict extends ParentPublic {
    use TraitPublic;
    public const C = 2;
}
echo ChildOwnConflict::C;

// 7. Conflicting constants between two traits still report PhanIncompatibleCompositionConstant.
trait TraitOther {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const C = 2;
}

class ChildTwoTraits extends ParentPublic {
    use TraitPublic, TraitOther;
}
echo ChildTwoTraits::C;

// 8. Incompatible typed trait constant overriding typed parent constant reports PhanConstantTypeMismatchInheritance.
class ParentTypedInt {
    public const int C = 0;
}
echo ParentTypedInt::C;

trait TraitTypedString {
    public const string C = 'x';
}

class ChildBadTypedTrait extends ParentTypedInt {
    use TraitTypedString;
}
echo ChildBadTypedTrait::C;

// 9. Covariant typed trait constant overriding wider parent constant is allowed.
class ParentTypedUnion {
    public const int|string C = 0;
}
echo ParentTypedUnion::C;

class ChildCovariantTrait extends ParentTypedUnion {
    use TraitTypedString;
}
echo ChildCovariantTrait::C;

// 10. Untyped trait constant overriding typed parent constant reports PhanConstantTypeMismatchInheritance.
class ChildUntypedTrait extends ParentTypedInt {
    use TraitPublic;
}
echo ChildUntypedTrait::C;

// 11. Private typed parent constant can be overridden with an unrelated type by a trait.
class ParentPrivateTyped {
    private const int C = 0;
    public static function getParent(): int {
        return self::C;
    }
}
echo ParentPrivateTyped::getParent();

class ChildPrivateTyped extends ParentPrivateTyped {
    use TraitTypedString;
}
echo ChildPrivateTyped::C;

// 12. Trait-relative `self` type resolves to the using class and is covariant with parent `self`.
class ParentSelfTyped {
    public const ?self C = null;
}
var_dump(ParentSelfTyped::C);

trait TraitSelfTyped {
    public const ?self C = null;
}

class ChildSelfTyped extends ParentSelfTyped {
    use TraitSelfTyped;
}
var_dump(ChildSelfTyped::C);

// 13. A suppression on the class using the trait applies to the issues reported for a constant of the trait.
/** @suppress PhanConstantTypeMismatchInheritance */
class ChildBadTypedTraitSuppressed extends ParentTypedInt {
    use TraitTypedString;
}
echo ChildBadTypedTraitSuppressed::C;

/** @suppress PhanConstantAccessSignatureMismatch */
class ChildNarrowedSuppressed extends ParentPublic {
    use TraitProtected;
    public static function get(): int {
        return self::C;
    }
}
echo ChildNarrowedSuppressed::get();

/** @suppress PhanAccessOverridesFinalConstant */
class ChildFinalSuppressed extends ParentFinal {
    use TraitPublic;
}
echo ChildFinalSuppressed::C;

// 14. `self` in the type of a trait constant is bound to the class using the trait, and subclasses inherit the constant
// with that binding: a trait of a subclass can override it with the type of the class using the trait (PHP accepts this).
trait TraitSelfBound {
    public const ?self C = null;
}

class SelfBoundBase {
    use TraitSelfBound;
}
var_dump(SelfBoundBase::C);

class SelfBoundMiddle extends SelfBoundBase {}

trait TraitSelfBoundOverride {
    public const ?SelfBoundBase C = null;
}

class SelfBoundChildTrait extends SelfBoundMiddle {
    use TraitSelfBoundOverride;
}
var_dump(SelfBoundChildTrait::C);

// 15. A constant declared in a subclass can override it with the type of the class using the trait (PHP accepts this).
class SelfBoundChildClass extends SelfBoundMiddle {
    public const ?SelfBoundBase C = null;
}
var_dump(SelfBoundChildClass::C);

// 16. A subclass that uses the trait again binds `self` to itself, so its subclass cannot override the constant with the type
// of the first class using the trait (PHP: "Type of SelfBoundReimportedChild::C must be compatible with SelfBoundReimported::C
// of type ?SelfBoundReimported").
class SelfBoundReimported extends SelfBoundBase {
    use TraitSelfBound;
}

class SelfBoundReimportedChild extends SelfBoundReimported {
    public const ?SelfBoundBase C = null;
}
var_dump(SelfBoundReimportedChild::C);

// 17. `self` in the type of a constant that a trait imports from another trait is bound to the class using the outer trait.
// PHP accepts the override with the type of that class, and rejects the override with an unrelated class
// ("Type of SelfBoundOuterChildBad::C must be compatible with SelfBoundOuterBase::C of type ?SelfBoundOuterBase").
trait TraitSelfBoundOuter {
    use TraitSelfBound;
}

class SelfBoundOuterBase {
    use TraitSelfBoundOuter;
}

class SelfBoundOuterMiddle extends SelfBoundOuterBase {}

class SelfBoundOuterChild extends SelfBoundOuterMiddle {
    public const ?SelfBoundOuterBase C = null;
}
var_dump(SelfBoundOuterChild::C);

class SelfBoundOuterChildBad extends SelfBoundOuterMiddle {
    public const ?SelfBoundBase C = null;
}
var_dump(SelfBoundOuterChildBad::C);

// 18. The binding is inherited three levels below the class using the trait (PHP accepts this).
class SelfBoundLevel1 extends SelfBoundBase {}

class SelfBoundLevel2 extends SelfBoundLevel1 {}

class SelfBoundLevel3 extends SelfBoundLevel2 {
    public const ?SelfBoundBase C = null;
}
var_dump(SelfBoundLevel3::C);

// 19. An untyped trait constant cannot override a typed constant of an internal class
// (PHP: "Type of ChildOfDateTimeWithTrait::ATOM must be compatible with DateTimeInterface::ATOM of type string").
// This is also reported when the PHP version running Phan is newer than the target version: DateTime::ATOM has its type since PHP 8.4.
trait TraitUntypedAtom {
    public const ATOM = 'custom';
}

class ChildOfDateTimeWithTrait extends \DateTime {
    use TraitUntypedAtom;
}
var_dump(ChildOfDateTimeWithTrait::ATOM);

// 20. A class narrowing the declared type of a trait constant with the same value still fails composition in PHP 8.5,
// even when extending a parent with the same constant.
class ParentUnionM {
    public const int|string M = 0;
}
echo ParentUnionM::M;

trait TraitUnionM {
    public const int|string M = 1;
}

class ChildNarrowedCompositionM extends ParentUnionM {
    use TraitUnionM;
    public const int M = 1;
}
echo ChildNarrowedCompositionM::M;

// 21. Deferred nested trait conflict is rechecked in the concrete using class.
class OtherN {}
trait TraitInnerSelfN {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const self|int N = 0;
}
trait TraitOuterOtherN {
    use TraitInnerSelfN;
    public const OtherN|int N = 0;
}
class ClassUsesOuterN {
    use TraitOuterOtherN;
}
var_dump(ClassUsesOuterN::N, new OtherN());

// 22. A trait constant typed with `static` is covariant with a parent `self` constant on the using subclass.
class ParentSelfO {
    public const self|int O = 0;
}
echo ParentSelfO::O;

trait TraitStaticO {
    public const static|int O = 0;
}

class ChildStaticO extends ParentSelfO {
    use TraitStaticO;
}
var_dump(ChildStaticO::O);

// Unrelated parent type still fails covariance when overridden with `static`.
class ParentOtherO {
    public const OtherN|int O = 0;
}
echo ParentOtherO::O;

class ChildBadStaticO extends ParentOtherO {
    use TraitStaticO;
}
var_dump(ChildBadStaticO::O);
