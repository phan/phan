<?php
// Regression test for https://github.com/phan/phan/issues/5558
// A variable typed `class-string<Foo>` didn't resolve to Foo for static calls ($class::method()),
// unlike an actual Foo-typed variable or `new $class()` (both already worked). This meant the
// return type of a valid static call was inferred as empty, and typos in the method name went
// completely unchecked (no PhanUndeclaredStaticMethod).

class Foo5935 {
    public static function bar(): Bar5935 {
        return new Bar5935();
    }

    public function instanceMethod(): void {
    }
}

class Bar5935 {
    public function output(): void {
        echo "Hello";
    }
}

/**
 * @param class-string<Foo5935> $class
 */
function testValidMethodViaClassString(string $class) {
    $bar = $class::bar();
    '@phan-debug-var $bar';
    $bar->output();
}

/**
 * @param class-string<Foo5935> $class
 */
function testInvalidMethodViaClassString(string $class) {
    $bar = $class::not_a_function();
    '@phan-debug-var $bar';
}

/**
 * @param class-string $class
 */
function testBareClassStringUnaffected(string $class) {
    // No template parameter - Phan genuinely can't know the class, this must stay unresolved
    // (no crash, no incorrect resolution).
    $bar = $class::bar();
    '@phan-debug-var $bar';
}

// Control cases: an actual object-typed parameter, to lock in unchanged behavior.
function testValidMethodViaInstance(Foo5935 $class) {
    $bar = $class::bar();
    '@phan-debug-var $bar';
    $bar->output();
}

function testInvalidMethodViaInstance(Foo5935 $class) {
    $bar = $class::not_a_function();
    '@phan-debug-var $bar';
}

/**
 * @param class-string<Foo5935> $class
 */
function testInstanceSyntaxOnClassStringMustNotResolve(string $class) {
    // $class is genuinely a string at runtime - instance-call syntax on it must NOT resolve
    // as if $class were a Foo5935 instance, unlike the static-call ($class::method()) cases
    // above. Must warn (e.g. PhanNonClassMethodCall), not silently accept this.
    $x = $class->instanceMethod();
    '@phan-debug-var $x';
}

/**
 * @param ?class-string<Foo5935> $class
 */
function testNullableClassStringResolvesLikeNullableObject(?string $class) {
    // $class could be null at runtime (so could a nullable Foo5935 $obj, and
    // `$obj::bar()` fatals identically in that case) - deliberately not special-cased: a
    // nullable class-string resolves fully (no "possibly null" warning), matching how a
    // nullable object receiver is already resolved and validated elsewhere in Phan.
    $bar = $class::bar();
    '@phan-debug-var $bar';
}

/**
 * @param ?class-string<Foo5935> $class
 */
function testNullableClassStringStillValidatesMembers(?string $class) {
    // Full member validation must still happen for the nullable case, matching how
    // `?Foo5935 $obj; $obj::not_a_function();` is already validated.
    $class::not_a_function();
}

/**
 * @template T
 */
class Box5935 {
    /** @return T */
    public static function make() {
        throw new \RuntimeException("stub for testing template resolution, not called");
    }
}

/**
 * @param class-string<Box5935<int>> $class
 */
function testTemplateResolvesFromClassString(string $class) {
    // Resolving the *class part* of the static call through class-string<Box5935<int>> must
    // resolve class-level T against Box5935<int> (i.e. T=int), not against the class-string
    // type itself - which isn't an object with a known FQSEN and so would leave T unresolved.
    $value = $class::make();
    '@phan-debug-var $value';
}

/**
 * @template T
 */
class Box5935b {
    /** @param T $value */
    public static function accept($value): void {
    }
}

/**
 * @param class-string<Box5935b<int>> $class
 */
function testTemplateArgumentValidationFromClassString(string $class) {
    // The Method object resolved via a class-string<Box<int>> receiver must have class-level T
    // substituted with int the same way argument validation would for an actual Box<int>
    // instance - this exercises a separate resolveTemplateType() call site
    // (ContextNode::getMethodListInternal(), used for argument/visibility checking) from
    // testTemplateResolvesFromClassString() above (UnionTypeVisitor::visitMethodCall(), used
    // only for return type inference).
    $class::accept('not_an_int');
}

class UnionClassA5935 {
    public function onlyOnA5935(): void {
    }
}

class UnionClassB5935 {
    public static function onlyOnB5935(): void {
    }
}

/**
 * @param UnionClassA5935|class-string<UnionClassB5935> $receiver
 */
function testClassStringAlternativeInUnion($receiver) {
    // UnionClassA5935 resolves first and doesn't declare onlyOnB5935() - the class-string
    // alternative (UnionClassB5935, which does) must still be considered, not discarded just
    // because $class_list was already non-empty from the other union member.
    $receiver::onlyOnB5935();
}

/**
 * @param class-string<Foo5935> $class
 */
function testDynamicClassConstMustNotResolve(string $class) {
    // `$class::class` is a TypeError at runtime for a plain string receiver (unlike
    // `$obj::class` for an object, or the literal `Foo::class`), so a class-string-typed
    // variable must NOT be treated as valid here even though `$class::method()` is.
    $x = $class::class;
    '@phan-debug-var $x';
}

/**
 * @template T of Foo5935
 * @param class-string<T> $class
 */
function testBoundedTemplateClassStringResolves(string $class) {
    // T has no concrete FQSEN of its own, but its bound (Foo5935) does - member lookup and
    // return-type inference should go through the bound, the same as class-string<Foo5935>
    // would, without collapsing the *result* of a generic call (see
    // testBoundedTemplateClassStringPreservesGenericity below) to that concrete bound.
    $bar = $class::bar();
    '@phan-debug-var $bar';
    $bar->output();
    $class::not_a_real_method();
}

/**
 * @template T of Foo5935
 * @param class-string<T> $class
 * @return T
 */
function testBoundedTemplateClassStringPreservesGenericity(string $class) {
    // `new $class()` must still resolve to the (possibly narrower) template type T, not
    // collapse to the concrete bound Foo5935 - otherwise this legitimately-generic factory
    // would falsely mismatch its own `@return T`.
    return new $class();
}
