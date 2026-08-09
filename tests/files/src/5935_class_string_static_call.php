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

class Foo5935c {
    public static function make(): static {
        return new static();
    }
}

/**
 * @template T of Foo5935c
 * @param class-string<T> $class
 * @return T
 */
function testBoundedTemplateClassStringLateStaticBinding(string $class) {
    // `make()`'s `static` return type must resolve back to T (the represented template), not
    // to the concrete bound Foo5935c used only for member lookup - otherwise this legitimately
    // generic factory would falsely mismatch its own `@return T`, and would lose subtype
    // information at call sites that pass a narrower class-string (see the Sub5935c case below).
    $x = $class::make();
    '@phan-debug-var $x';
    return $x;
}

class Sub5935c extends Foo5935c {
}

function testBoundedTemplateClassStringLateStaticBindingCallSite(): void {
    $y = testBoundedTemplateClassStringLateStaticBinding(Sub5935c::class);
    '@phan-debug-var $y';
}

class Foo5935d {
    /** @return self */
    public static function makeSelf() {
        return new self();
    }
}

/**
 * @template T of Foo5935d
 * @param class-string<T> $class
 */
function testBoundedTemplateClassStringSelfReturn(string $class) {
    // Unlike `static`, a `self` return type always means the declaring class - it must NOT be
    // substituted with the template T.
    $x = $class::makeSelf();
    '@phan-debug-var $x';
}

class Foo5935eA {
    public static function make(): static {
        return new static();
    }
}

class Foo5935eB {
    public static function make(): static {
        return new static();
    }
}

/**
 * @template T of Foo5935eA
 * @template U of Foo5935eB
 * @param class-string<T>|class-string<U> $class
 */
function testUnionOfClassStringsKeepsPerAlternativeTemplates(string $class) {
    // Each class-string alternative must map back to its OWN template - the result is T|U,
    // not whichever template happened to be scanned last applied to both classes.
    $x = $class::make();
    '@phan-debug-var $x';
}

/**
 * @template T of Foo5935eA
 * @template U of Foo5935eA
 * @param class-string<T>|class-string<U> $class
 */
function testUnionOfClassStringsSharingOneBound(string $class) {
    // T and U share the same bound (Foo5935eA), so both map to the same resolved class - both
    // must still be preserved (result T|U), rather than one overwriting the other.
    $x = $class::make();
    '@phan-debug-var $x';
}

/**
 * @template T of Foo5935c
 * @param Foo5935c|class-string<T> $receiver
 */
function testConcreteAlternativeNotOverriddenByTemplate($receiver) {
    // Both alternatives resolve to the same Foo5935c class, but the plain object alternative
    // can genuinely return Foo5935c - substituting `static` with T for the whole class entry
    // would unsoundly discard that. Keep the concrete resolution when the class is also
    // reachable without going through a class-string.
    $x = $receiver::make();
    '@phan-debug-var $x';
}

/** @template T */
class Box5935f {
    public static function make(): static {
        return new static();
    }
}

/**
 * @param class-string<Box5935f<int>> $class
 * @return Box5935f<int>
 */
function testGenericArgumentsPreservedForStaticReturn(string $class) {
    // classListFromNode() reduces `class-string<Box5935f<int>>` to the bare Box5935f class for
    // member lookup, so resolving `static` against that class context would drop the <int>.
    // The generic receiver type must be used for the substitution instead.
    $x = $class::make();
    '@phan-debug-var $x';
    return $x;
}

interface Maker5935g {
    public static function make(): static;
}
interface Marker5935g {
}

/**
 * @template T of Maker5935g&Marker5935g
 * @param class-string<T> $class
 * @return T
 */
function testIntersectionBoundPreservesTemplate(string $class) {
    // The bound is an intersection, which has no single FQSEN - member lookup flattens it to
    // find Maker5935g::make(), so the represented template must be associated with each
    // flattened part. Otherwise `static` resolves to \Maker5935g, dropping the Marker5935g
    // constraint and falsely mismatching this function's own `@return T`.
    $x = $class::make();
    '@phan-debug-var $x';
    return $x;
}

class Foo5935h {
    /** @return static[] */
    public static function makeMany() {
        return [new static()];
    }

    /** @return ?static */
    public static function maybeMake() {
        return new static();
    }
}

/**
 * @template T of Foo5935h
 * @param class-string<T> $class
 * @return T[]
 */
function testNestedStaticInArrayReturn(string $class) {
    // Method::getUnionType() expands `static[]` to `static[]|Foo5935h[]`, so substituting on it
    // while removing only the bare `Foo5935h` type would leave `Foo5935h[]` behind and infer
    // `T[]|Foo5935h[]`. The substitution must run on the unmodified (still-abstract) return type.
    $x = $class::makeMany();
    '@phan-debug-var $x';
    return $x;
}

/**
 * @template T of Foo5935h
 * @param class-string<T> $class
 * @return ?T
 */
function testNestedStaticInNullableReturn(string $class) {
    // Same for `?static`, which expands to `?static|?Foo5935h`.
    $y = $class::maybeMake();
    '@phan-debug-var $y';
    return $y;
}

class Base5935i {
    /** @return self */
    public static function makeSelf() {
        return new self();
    }
}

class Child5935i extends Base5935i {
}

/**
 * @template T of Child5935i
 * @param class-string<T> $class
 */
function testInheritedSelfResolvesToDeclaringClass(string $class) {
    // `self` means the class that DECLARED the method (Base5935i), not the receiver - even
    // though the receiver is `class-string<T>` bounded by the subclass Child5935i, and even
    // though `static` in the same position would resolve to T.
    $x = $class::makeSelf();
    '@phan-debug-var $x';
}

interface Marker5935j {
}

/**
 * @template T of Foo5935c
 * @param (Foo5935c&Marker5935j)|class-string<T> $receiver
 */
function testIntersectionConcreteAlternativeNotOverridden($receiver) {
    // Like testConcreteAlternativeNotOverriddenByTemplate, but the concrete alternative is an
    // intersection. An IntersectionType has no single FQSEN, yet classListFromNode() flattens it
    // and resolves the same Foo5935c class - so its parts must still count as concretely
    // referenced, or the template substitution would discard this alternative's own result.
    $x = $receiver::make();
    '@phan-debug-var $x';
}

class Foo5935k {
    /**
     * A method-level @template that IS used in the return type installs a dependent-return
     * closure, which derives its result from Method::getUnionType() - so the result carries
     * the same `static[]` -> `static[]|Foo5935k[]` expansion.
     * @template U
     * @param U $u
     * @return static[]|U
     * @suppress PhanParamNameIndicatingUnused
     */
    public static function makeMany($u) {
        return [new static()];
    }
}

/**
 * @template T of Foo5935k
 * @param class-string<T> $class
 */
function testDependentReturnTypeNestedStatic(string $class) {
    // The dependent result (which carries the argument-derived substitution of U, here the
    // literal 1) must be kept, while the declaring-class expansion `Foo5935k[]` is removed -
    // so this is `1|T[]`, not `1|T[]|Foo5935k[]`.
    $x = $class::makeMany(1);
    '@phan-debug-var $x';
}

class Foo5935m {
    /**
     * @template U
     * @param U $u
     * @return static|U
     * @suppress PhanParamNameIndicatingUnused
     */
    public static function makeOrArg($u) {
        return new static();
    }
}

/**
 * @template T of Foo5935m
 * @param class-string<T> $class
 */
function testDependentReturnTypeColliding(string $class, Foo5935m $arg) {
    // U resolves to Foo5935m - the SAME type Method::getUnionType() appends as the `static`
    // expansion. The two origins are indistinguishable by equality, so the genuine U result
    // must not be dropped: this is `T|Foo5935m`, not just `T`.
    $x = $class::makeOrArg($arg);
    '@phan-debug-var $x';
}
