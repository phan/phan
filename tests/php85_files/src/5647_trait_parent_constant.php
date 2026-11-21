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
