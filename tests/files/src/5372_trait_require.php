<?php

namespace NS5372;

class BaseClass {}

interface FooInterface {}

/**
 * @require-extends BaseClass
 */
trait RequiresBase
{
    public function mustExtendBase(): void {}
}

trait RequiresBaseViaTrait
{
    use RequiresBase;
}

/**
 * @psalm-require-implements FooInterface
 */
trait RequiresInterface
{
    public function mustImplementInterface(): void {}
}

class ValidChild extends BaseClass
{
    use RequiresBase;
}

class ValidImplementation implements FooInterface
{
    use RequiresInterface;
}

class IndirectValidChild extends BaseClass
{
    use RequiresBaseViaTrait;
}

class InvalidChild
{
    use RequiresBase;
}

class IndirectInvalidChild
{
    use RequiresBaseViaTrait;
}

class InvalidImplementation
{
    use RequiresInterface;
}

function test(ValidChild $child, ValidImplementation $impl): void
{
    $child->mustExtendBase();
    $impl->mustImplementInterface();
}
