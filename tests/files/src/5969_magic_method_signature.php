<?php
namespace NS5969;

// PHP rejects these declarations of magic methods when it compiles the class
class Arguments {
    public function __destruct($x) {}
    public function __clone($x) {}
    public function __get() {}
    public function __set($name) {}
    public function __unset($name, $extra = 1) {}
    public function __call(...$args) {}
    public function __toString($x) { return ''; }
    public function __sleep($x) { return []; }
}
class ByReference {
    public function __get(&$name) {}
    public function __set($name, &$value) {}
    public static function __callStatic($name, &$args) {}
}
class StaticRules {
    public static function __construct() {}
    public static function __get($name) {}
    public static function __call($name, $args) {}
    public function __callStatic($name, $args) {}
    public function __set_state($properties) {}
    public static function __invoke() {}
    public static function __toString() { return ''; }
}
class ParameterTypes {
    public function __get(int $name) {}
    public function __isset(\Stringable $name) { return true; }
    public function __call(string $name, string $args) {}
    public static function __callStatic(?int $name, array $args) {}
    public function __unserialize(string $data) {}
    public static function __set_state(object $properties) {}
}
class ReturnTypes {
    public function __construct(): void {}
    public function __destruct(): void {}
    public function __clone(): int { return 1; }
    public function __set($name, $value): bool { return true; }
    public function __unset($name): mixed { return null; }
    public function __isset($name): ?bool { return true; }
    public function __toString(): string|\Stringable { return ''; }
    public function __debugInfo(): iterable { return []; }
    public function __serialize(): ?array { return []; }
    public function __unserialize(array $data): int { return 1; }
    public static function __set_state(array $properties): ?object { return null; }
    public function __sleep(): string { return ''; }
    public function __wakeup(): bool { return true; }
}
// PHP warns about these
class Visibility {
    private function __get($name) {}
    protected function __set($name, $value) {}
    private function __call($name, $args) {}
    protected static function __callStatic($name, $args) {}
    private function __invoke() {}
    protected function __wakeup() {}
    // Not checked for these
    private function __construct() {}
    private function __clone() {}
}
interface InterfaceRules {
    public function __get();
    public function __toString(): int;
}
abstract class AbstractRules {
    abstract public function __CALL($name);
}
trait TraitRules {
    public function __isset($name): int { return 1; }
}

// These are valid
class Valid {
    public function __construct(private int $x = 0) {}
    public function __destruct() {}
    public function __clone(): void {}
    public function __get(string $name): mixed { return null; }
    public function __set(string|int $name, mixed $value): void {}
    public function __unset(mixed $name): void {}
    public function __isset(?string $name): bool { return false; }
    public function __call(string $name, array $args): mixed { return null; }
    public static function __callStatic(string $name, array|string $args): mixed { return null; }
    public function __toString(): string { return ''; }
    public function __debugInfo(): array { return []; }
    public function __serialize(): array { return []; }
    public function __unserialize(array $data): void {}
    public static function __set_state(array $properties): static { return new static(); }
    public function __invoke(&$a, int ...$rest): int { return $a; }
    public function __sleep(): array { return []; }
    public function __wakeup(): void {}
}
class ValidWithoutTypes {
    public function __get($name) { return null; }
    public function __set($name, $value) {}
    public function __isset($name) { return false; }
    public function __unset($name) {}
    public function __call($name, $args) { return null; }
    public static function __callStatic($name, $args) { return null; }
    public function __toString() { return ''; }
    public static function __set_state($properties) { return new self(); }
}
class ValidOtherTypes {
    public function __clone(): never { throw new \Exception(); }
    public function __isset($name): bool { return false; }
    // PHP 8.1 rejects this: iterable is only Traversable|array since PHP 8.2
    public function __call($name, iterable $args) { return null; }
    public static function __set_state(array $properties): self|\stdClass { return new self(); }
    // Not a magic method
    public function __other(int $a, &$b): int { return $a; }
}
class Suppressed {
    /** @suppress PhanInvalidMagicMethodSignature */
    public function __get() {}
    /** @suppress PhanMagicMethodNonPublic */
    private function __set($name, $value) {}
}
// `parent` in a trait is a declared type, although the class is not known
trait ParentTypes {
    public function __get(parent $name) {}
    public function __toString(): parent { throw new \Exception(); }
    public function __destruct(): parent {}
    // Valid
    public static function __set_state(array $properties): parent { throw new \Exception(); }
}
// PHP does not check the methods that a trait alias creates
trait AliasTrait {
    public function notMagic() {}
}
class UsesAlias {
    use AliasTrait { notMagic as __get; }
}
