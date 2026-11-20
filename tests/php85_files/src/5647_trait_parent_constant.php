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
