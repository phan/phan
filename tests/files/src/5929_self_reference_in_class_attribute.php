<?php

#[Attribute(Attribute::TARGET_CLASS)]
class FoobarAttribute
{
    public function __construct(public string $string) {}
}

#[FoobarAttribute(self::FOOBAR_CLASS_CONST)]
class FoobarClass
{
    public const FOOBAR_CLASS_CONST = 'foobar';
}

