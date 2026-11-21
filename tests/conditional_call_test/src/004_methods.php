<?php

class SafeChecker
{
    public static function check(int|string $x): bool
    {
        return is_string($x);
    }

    public static function test_self(int|string $x): void
    {
        if (self::check($x)) {
            '@phan-debug-var $x';
        }
    }
}

class Validator
{
    public function isValid(int|string $x): bool
    {
        return is_string($x);
    }
}

class ValidatorA
{
    public function accepts(int|string $x): bool
    {
        return is_string($x);
    }
}

class ValidatorB
{
    public function accepts(int|string $x): bool
    {
        return is_string($x);
    }
}

class BaseChecker
{
    public function verify(int|string $x): bool
    {
        return is_string($x);
    }
}

class ChildChecker extends BaseChecker
{
}

class LocalChild extends ExternalBase
{
}

function test_static_method(int|string $x): void
{
    if (SafeChecker::check($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
}

function test_instance_method(Validator $v, int|string $x): void
{
    if ($v->isValid($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
}

function test_one_class_of_two(ValidatorA $a, int|string $x): void
{
    if ($a->accepts($x)) {
        '@phan-debug-var $x';
    }
}

function test_union_of_two_classes(ValidatorA|ValidatorB $v, int|string $x): void
{
    // Unchanged: the call can be ValidatorA::accepts() or ValidatorB::accepts()
    if ($v->accepts($x)) {
        '@phan-debug-var $x';
    }
}

function test_inherited(ChildChecker $c, int|string $x): void
{
    if ($c->verify($x)) {
        '@phan-debug-var $x';
    }
}

function test_lazily_inherited(LocalChild $c, int|string $x): void
{
    if ($c->externalVerify($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
}

function test_nullsafe(?Validator $v, int|string $x): void
{
    if ($v?->isValid($x)) {
        '@phan-debug-var $v, $x';
    }
    // Unchanged: isValid() is not called when $v is null
    if (!$v?->isValid($x)) {
        '@phan-debug-var $x';
    }
}

class ValidatorHolder
{
    /** @var Validator */
    public $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }
}

function test_nullsafe_chain(?ValidatorHolder $holder, int|string $x): void
{
    if ($holder?->validator->isValid($x)) {
        '@phan-debug-var $x';
    }
    // Unchanged: isValid() is not called when $holder is null
    if (!$holder?->validator->isValid($x)) {
        '@phan-debug-var $x';
    }
}

class MagicValidator
{
    /** @param list<mixed> $args */
    public function __call(string $name, array $args): bool
    {
        return true;
    }
}

function test_union_with_class_lacking_method(ValidatorA|Validator $v, int|string $x): void
{
    // Unchanged: a subclass of Validator may declare accepts()
    if ($v->accepts($x)) {
        '@phan-debug-var $x';
    }
}

function test_union_with_magic_method(ValidatorA|MagicValidator $v, int|string $x): void
{
    // Unchanged: the call can be ValidatorA::accepts() or MagicValidator::__call()
    if ($v->accepts($x)) {
        '@phan-debug-var $x';
    }
}

enum CondEnum
{
    case A;
}

function test_method_of_internal_class(): void
{
    // The closure for \ReflectionEnum::hasCase is attached when ReflectionEnum is loaded (in the analysis phase)
    $r = new ReflectionEnum(CondEnum::class);
    if ($r->hasCase('A')) {
        '@phan-debug-var $checked';
    }
}
