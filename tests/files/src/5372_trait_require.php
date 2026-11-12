<?php

namespace NS5372;

class BaseClass {
    public string $baseProperty;
    public function baseMethod(string $param): void {}
}

interface FooInterface {
    public function interfaceMethod(): void;
}

/**
 * @require-extends BaseClass
 */
trait RequiresBase
{
    public function mustExtendBase(): void {
        $this->baseMethod($this->baseProperty);
    }
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
    public function mustImplementInterface(): void {
        $this->interfaceMethod();
    }
}

class ValidChild extends BaseClass
{
    use RequiresBase;
}

abstract class ValidImplementation implements FooInterface
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

abstract class InvalidImplementation
{
    use RequiresInterface;
}

function test(ValidChild $child, ValidImplementation $impl): void
{
    $child->mustExtendBase();
    $impl->mustImplementInterface();
}
