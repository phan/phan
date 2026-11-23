<?php
namespace NS012;

class NullableDebugInfo {
    // Deprecated in PHP 8.6
    public function __debugInfo(): ?array { return null; }
}
class UnionDebugInfo {
    public function __debugInfo(): array|null { return []; }
}
class ValidDebugInfo {
    public function __debugInfo(): array { return []; }
}
// Magic methods that return nothing cannot be #[\NoDiscard] (PHP 8.5+)
class NoDiscardRules {
    #[\NoDiscard] public function __construct() {}
    #[\NoDiscard] public function __clone() {}
    #[\NoDiscard] public function __set($name, $value) {}
    #[\NoDiscard] public function __wakeup() {}
    // Valid
    #[\NoDiscard] public function __get($name) { return 1; }
    #[\NoDiscard] public function __toString(): string { return ''; }
}
// iterable is Traversable|array since PHP 8.2, so it allows the array that __call() is given
class IterableArguments {
    public function __call(string $name, iterable $args): mixed { return [$name, $args]; }
}
