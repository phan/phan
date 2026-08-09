<?php
// Regression test for https://github.com/phan/phan/issues/5558
// With strict_method_checking, a `class-string<B>` alternative in a union receiver must be
// checked like the class it represents. getClassList() resolves it to B for static calls, so
// isDefinitelyPossiblyUndeclaredMethod() must consider B too - otherwise a union such as
// `A|class-string<B>` where only A declares the method silently suppressed the expected
// PhanPossiblyUndeclaredMethod.

class HasMethod322 {
    public static function shared(): void {
    }
}

class LacksMethod322 {
}

/**
 * @param HasMethod322|class-string<LacksMethod322> $receiver
 */
function test_class_string_alternative_322($receiver): void {
    $receiver::shared();
}

/**
 * Control case: the same shape with two plain object types, which has always warned.
 * @param HasMethod322|LacksMethod322 $receiver
 */
function test_plain_object_alternative_322($receiver): void {
    $receiver::shared();
}

/**
 * Must NOT warn - every alternative declares the method.
 * @param HasMethod322|class-string<HasMethod322> $receiver
 */
function test_all_alternatives_declare_322($receiver): void {
    $receiver::shared();
}
