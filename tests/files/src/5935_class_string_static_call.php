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
function testNullableClassStringMustStillWarn(?string $class) {
    // $class could be null at runtime, in which case $class::bar() fatals. The pre-existing
    // "possibly non-class type" warning for this must keep firing, even though the return
    // type can still be usefully inferred assuming the non-null case (matching how the
    // codebase already treats other "possibly invalid, but useful if valid" call sites).
    $bar = $class::bar();
    '@phan-debug-var $bar';
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
