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

echo Child::X, Child::C, Bad::NOPE, Bad::P, Suit::C, Suit::Hearts->value, UsesTrait::C, UsesTraitWithoutMatch::C, Base::UNRELATED;
