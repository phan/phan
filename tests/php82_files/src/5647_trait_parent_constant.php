<?php

namespace Issue5647;

// 1. Private parent constant does not conflict with trait constant in any PHP version.
class ParentWithPrivate {
    private const C = 0;
    public static function getParent(): int {
        return self::C;
    }
}
echo ParentWithPrivate::getParent();

trait TraitPublic {
    public const C = 1;
}

trait TraitPrivate {
    private const C = 1;
}

class ChildPrivateToPublic extends ParentWithPrivate {
    use TraitPublic;
}
echo ChildPrivateToPublic::C;

class ChildPrivateToPrivate extends ParentWithPrivate {
    use TraitPrivate;
    public static function get(): int {
        return self::C;
    }
}
echo ChildPrivateToPrivate::get();

// 2. Non-private parent constant with a different value still conflicts before PHP 8.5.
class ParentWithPublic {
    public const C = 0;
}
echo ParentWithPublic::C;

class ChildPublicConflict extends ParentWithPublic {
    use TraitPublic;
}
echo ChildPublicConflict::C;

// 3. Class redeclaring the constant compatibly with the trait overrides the parent constant cleanly.
class ChildRedeclaresCompatible extends ParentWithPublic {
    use TraitPublic;
    public const C = 1;
}
echo ChildRedeclaresCompatible::C;

// 4. Class redeclaring the constant incompatibly with the trait still conflicts with the trait.
class ChildRedeclaresIncompatible extends ParentWithPublic {
    use TraitPublic;
    public const C = 2;
}
echo ChildRedeclaresIncompatible::C;

// 5. Untyped override of an internal constant stays valid when targeting PHP 8.2, even on PHP 8.4+.
class ChildDateTime extends \DateTime {
    public const ATOM = 'custom';
}
echo ChildDateTime::ATOM;
