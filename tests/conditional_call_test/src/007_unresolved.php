<?php

// The closures do not run when the call may not have run the function or method that they are for.

function int_check(int|string $x): int
{
    return is_string($x) ? 1 : 0;
}

function test_strict_comparison_of_int(int|string $x): void
{
    // Unchanged: int_check($x) !== false is true for 0 and for 1
    if (int_check($x) !== false) {
        '@phan-debug-var $x';
    }
    // Unchanged: int_check($x) !== true is true for 0 and for 1
    if (int_check($x) !== true) {
        '@phan-debug-var $x';
    }
    if (int_check($x) == true) {
        '@phan-debug-var $x';
    }
    if (int_check($x)) {
        '@phan-debug-var $x';
    }
}

class PrivateChecker
{
    private function check(int|string $x): bool
    {
        return is_string($x);
    }

    /** @param list<mixed> $args */
    public function __call(string $name, array $args): bool
    {
        return true;
    }

    public function test_inside(int|string $x): void
    {
        if ($this->check($x)) {
            '@phan-debug-var $x';
        }
    }
}

function test_inaccessible_method(PrivateChecker $c, int|string $x): void
{
    // Unchanged: this calls PrivateChecker::__call(), not the private method check()
    if ($c->check($x)) {
        '@phan-debug-var $x';
    }
}

final class FinalWithoutMethod
{
    public function unused(int|string $x): bool
    {
        return is_string($x);
    }
}

function test_union_with_final_class_lacking_method(ValidatorA|FinalWithoutMethod $v, int|string $x): void
{
    // Calling accepts() on a FinalWithoutMethod would throw, so the call can only be ValidatorA::accepts()
    if ($v->accepts($x)) {
        '@phan-debug-var $x';
    }
}

function test_union_with_object(ValidatorA|object $v, int|string $x): void
{
    // Unchanged: the object can be of any class
    if ($v->accepts($x)) {
        '@phan-debug-var $x';
    }
}

/**
 * @param class-string<SafeChecker> $known
 * @param class-string $any
 */
function test_class_strings(string $known, string $any, string $name, int|string $x): void
{
    if ($known::check($x)) {
        '@phan-debug-var $x';
    }
    // Unchanged: the string can be the name of any class
    if ($any::check($x)) {
        '@phan-debug-var $x';
    }
    if ($name::check($x)) {
        '@phan-debug-var $x';
    }
}

class ValidatorFactory
{
    public static function make(): Validator
    {
        return new Validator();
    }
}

class FactoryHolder
{
    public function factory(): ValidatorFactory
    {
        return new ValidatorFactory();
    }
}

function test_nullsafe_chain_with_static_call(?FactoryHolder $holder, int|string $x): void
{
    if ($holder?->factory()::make()->isValid($x)) {
        '@phan-debug-var $x';
    }
    // Unchanged: isValid() is not called when $holder is null
    if (!$holder?->factory()::make()->isValid($x)) {
        '@phan-debug-var $x';
    }
}

class IntChecker
{
    public function check(int|string $x): int
    {
        return is_string($x) ? 1 : 0;
    }
}

function test_strict_comparison_of_methods(Validator $v, IntChecker $c, int|string $x): void
{
    if ($v->isValid($x) === true) {
        '@phan-debug-var $x';
    }
    if ($v->isValid($x) !== false) {
        '@phan-debug-var $x';
    }
    if (SafeChecker::check($x) === false) {
        '@phan-debug-var $x';
    }
    // Unchanged: $c->check($x) !== false is true for 0 and for 1
    if ($c->check($x) !== false) {
        '@phan-debug-var $x';
    }
    if ($c->check($x) == true) {
        '@phan-debug-var $x';
    }
}

class PrivateBindingParent
{
    private function check(int|string $x): bool
    {
        return is_string($x);
    }

    public function test(PrivateBindingChild $c, int|string $x): void
    {
        // Unchanged: in this class, $c->check() calls the private method of this class, not PrivateBindingChild::check()
        if ($c->check($x)) {
            '@phan-debug-var $x';
        }
    }
}

final class PrivateBindingChild extends PrivateBindingParent
{
    public function check(int|string $x): bool
    {
        return is_string($x);
    }
}

function test_public_method_of_subclass(PrivateBindingChild $c, int|string $x): void
{
    if ($c->check($x)) {
        '@phan-debug-var $x';
    }
}
