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
