<?php

namespace NS6011;

// (at)phpstan-assert-if-true about $this (the object that the method is called on) and about a property of $this.

class Test {
    /** @phpstan-assert-if-true TestMethod $this */
    public function isTestMethod(): bool { return false; }
    public function name(): string {
        if ($this->isTestMethod()) {
            return $this->methodName();
        }
        return $this->methodName(); // not a TestMethod here
    }
}
final class TestMethod extends Test {
    public function isTestMethod(): bool { return true; }
    public function methodName(): string { return 'm'; }
}
final class Config {
    private ?string $file;
    public ?int $count;
    protected ?Test $test;
    public function __construct(?string $file, ?int $count, ?Test $test) { $this->file = $file; $this->count = $count; $this->test = $test; }
    /** @phpstan-assert-if-true !null $this->file */
    public function hasFile(): bool { return $this->file !== null; }
    /** @phpstan-assert-if-true !null $this->count */
    public function hasCount(): bool { return $this->count !== null; }
    /** @psalm-assert-if-true =int $this->count */
    public function hasCountOnly(): bool { return $this->count !== null; }
    /** @phan-assert-if-true null $this->test */
    public function hasNoTest(): bool { return $this->test === null; }
    public function file(): string {
        if (!$this->hasFile()) {
            throw new \Exception();
        }
        return $this->file;
    }
    public function fileOrEmpty(): string {
        if ($this->hasFile()) {
            return $this->file;
        }
        return '';
    }
    public function unchecked(): string {
        return $this->file; // still reported
    }
    public function testName(): string {
        if ($this->hasNoTest()) {
            return '';
        }
        // The test is not null here, and a TestMethod in the body of the if statement.
        if ($this->test->isTestMethod()) {
            return $this->test->methodName();
        }
        return $this->test->methodName(); // not a TestMethod here
    }
}
function nameOf(Test $test): string {
    if ($test->isTestMethod()) {
        '@phan-debug-var $test';
        return $test->methodName();
    }
    return '';
}
function countOf(Config $config, Config $other): int {
    if ($config->hasCount()) {
        return $config->count;
    }
    if ($other->hasCountOnly()) {
        return $other->count;
    }
    $a = $config->count;
    $b = $other->count;
    // $a is null (the opposite of !null is assumed), $b is ?int (nothing is assumed for =int, or for the psalm- form).
    '@phan-debug-var $a, $b';
    return 0;
}
function nullsafe(?Config $config): ?int {
    if (!$config?->hasCount()) {
        // $config may be null: nothing is assumed about $config->count.
        return $config?->count;
    }
    return $config->count;
}
// A private property of another class is not read, and an assertion about it is of no use here: nothing is reported for it.
function fileOf(Config $config): bool {
    return $config->hasFile();
}

// Still reported: $this for a function and for a static method.
/** @phpstan-assert-if-true Test $this */
function notAMethod(): bool { return true; }
final class Statics {
    /** @phpstan-assert-if-true Test $this */
    public static function isTest(): bool { return true; }
    /** @phpstan-assert-if-true Test $missing */
    public function missing(): bool { return true; }
}

// (at)phpstan-assert and (at)psalm-assert without a condition are read like (at)phan-assert.
final class Assert {
    /**
     * @template ExpectedType of object
     * @param class-string<ExpectedType> $expected
     * @phpstan-assert =ExpectedType $actual
     */
    public static function assertInstanceOf(string $expected, mixed $actual): void {
        if (!$actual instanceof $expected) { throw new \Exception(); }
    }
    /** @phpstan-assert string $actual */
    public static function assertIsString(mixed $actual): void { if (!is_string($actual)) { throw new \Exception(); } }
    /** @phpstan-assert !null $actual */
    public static function assertNotNull(mixed $actual): void { if ($actual === null) { throw new \Exception(); } }
    /** @psalm-assert true $actual */
    public static function assertTrue(mixed $actual): void { if ($actual !== true) { throw new \Exception(); } }
    /** @phpstan-assert !numeric $actual the opposite of a type that Phan reads as a wider one is not assumed */
    public static function assertNotNumeric(mixed $actual): void { if (is_numeric($actual)) { throw new \Exception(); } }
    /** @phpstan-assert !null $this->value an assertion without a condition about a property is not read */
    public function assertHasValue(): void {}
    /** @phpstan-assert string $missing still reported */
    public static function assertMissing(): void {}
}
function asserts(mixed $a, ?\DateTime $d, mixed $s, int|string $n, mixed $t): void {
    Assert::assertInstanceOf(\ArrayObject::class, $a);
    Assert::assertNotNull($d);
    Assert::assertIsString($s);
    Assert::assertNotNumeric($n);
    Assert::assertTrue($t);
    '@phan-debug-var $a, $d, $s, $n, $t';
}

// The template types of the class are the ones of the object.
/** @template T */
final class Box {
    /** @var T|null */
    public $value;
    /** @param T|null $value */
    public function __construct($value) { $this->value = $value; }
    /** @phpstan-assert-if-true !null $this->value */
    public function hasValue(): bool { return $this->value !== null; }
}
/** @param Box<string> $box */
function boxed(Box $box): int {
    if ($box->hasValue()) {
        $value = $box->value;
        '@phan-debug-var $value';
        return strlen($value);
    }
    return 0;
}

// An assertion about a private property of the class that declares the method is not about the property that another class declares with that name.
class ParentWithPrivate {
    private ?string $file = 'x';
    /** @phpstan-assert-if-true !null $this->file */
    public function hasFile(): bool { return $this->file !== null; }
}
final class ChildWithOwn extends ParentWithPrivate {
    public ?string $file = null;
}
function ownFile(ChildWithOwn $child): ?string {
    if ($child->hasFile()) {
        $file = $child->file;
        '@phan-debug-var $file';
        return $file;
    }
    return null;
}

// (at)phpstan-assert keeps the types that are subtypes of the asserted type. (at)phan-assert replaces the type.
final class Narrow {
    /** @phpstan-assert Test $value */
    public static function assertTest(mixed $value): void { if (!$value instanceof Test) { throw new \Exception(); } }
    /** @phan-assert Test $value */
    public static function replaceWithTest(mixed $value): void { if (!$value instanceof Test) { throw new \Exception(); } }
}
function narrowed(TestMethod $a, TestMethod $b): void {
    Narrow::assertTest($a);
    Narrow::replaceWithTest($b);
    '@phan-debug-var $a, $b';
}
