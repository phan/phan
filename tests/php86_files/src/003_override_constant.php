<?php

namespace NS86OverrideConstant;

// PHP 8.6: #[\Override] on class constants (https://wiki.php.net/rfc/override_constants)

interface HasC
{
    const C = 1;
}

class Base
{
    protected const X = 'x';
    private const P = 1;
    public const UNRELATED = self::P;
}

class Child extends Base implements HasC
{
    #[\Override]
    public const X = 'y';

    #[\Override]
    public const C = 2;
}

class Bad extends Base
{
    // Fatal error: Bad::NOPE has #[\Override] attribute, but no matching parent constant exists
    #[\Override]
    public const NOPE = 3;

    // A private constant of the parent class is not overridden.
    #[\Override]
    public const P = 3;
}

enum Suit: string implements HasC
{
    #[\Override]
    const C = 3;

    // An enum case is a class constant
    #[\Override]
    case Hearts = 'H';
}

trait T
{
    // Not checked for the trait itself: the constant may override a constant of a class using the trait.
    #[\Override]
    public const C = 4;
}

class UsesTrait implements HasC
{
    use T;
}

// Fatal error: UsesTraitWithoutMatch::C has #[\Override] attribute, but no matching parent constant exists
class UsesTraitWithoutMatch
{
    use T;
}

// The constant also reaches a class through a trait using the trait.
trait UsesT
{
    use T;
}

class NestedWithMatch implements HasC
{
    use UsesT;
}

// Fatal error: NestedWithoutMatch::C has #[\Override] attribute, but no matching parent constant exists
class NestedWithoutMatch
{
    use UsesT;
}

// A class that declares the constant itself replaces the constant of the trait:
// the attribute of the trait's constant is not checked for it.
class RedeclaresConstant
{
    use T;

    public const C = 4;
}

// The attribute of the class's own constant is checked, and the constant of the trait is not a parent constant.
// Fatal error: RedeclaresWithOverride::C has #[\Override] attribute, but no matching parent constant exists
class RedeclaresWithOverride
{
    use T;

    #[\Override]
    public const C = 4;
}

// The same applies to the protected and private constants of a trait.
trait NonPublic
{
    #[\Override]
    protected const PROT = 1;

    #[\Override]
    private const PRIV = 2;

    public function sum(): int
    {
        return self::PROT + self::PRIV;
    }
}

class WithProtected
{
    protected const PROT = 1;
}

// Fatal error: NonPublicWithMatch::PRIV has #[\Override] attribute, but no matching parent constant exists
class NonPublicWithMatch extends WithProtected
{
    use NonPublic;
}

// Fatal error: NonPublicWithoutMatch::PROT has #[\Override] attribute, but no matching parent constant exists
class NonPublicWithoutMatch
{
    use NonPublic;
}

echo (new NonPublicWithMatch())->sum(), (new NonPublicWithoutMatch())->sum();
echo NestedWithMatch::C, NestedWithoutMatch::C, RedeclaresConstant::C, RedeclaresWithOverride::C;
echo Child::X, Child::C, Bad::NOPE, Bad::P, Suit::C, Suit::Hearts->value, UsesTrait::C, UsesTraitWithoutMatch::C, Base::UNRELATED;
