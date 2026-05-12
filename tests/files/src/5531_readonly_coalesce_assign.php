<?php
// @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicClassConstant, PhanPluginNoCommentOnClass, PhanUnreferencedPublicProperty

// Non-nullable readonly property: ??= is always safe.
// If uninitialized, assigns once. If already set, ??= short-circuits (no-op).
class NonNullableReadonly {
    public readonly string $foo;
    public readonly int $bar;

    public function init(): void {
        // OK: non-nullable readonly, ??= is a safe lazy initializer
        $this->foo ??= 'default';
        $this->bar ??= 42;
    }

    public function initInLoop(): void {
        // OK: even in a loop, the second iteration short-circuits because foo is non-null
        for ($i = 0; $i < 10; $i++) {
            $this->foo ??= "$i";
        }
    }
}

// Nullable readonly property: ??= could attempt to re-assign after a null initialization
class NullableReadonly {
    public readonly ?string $foo;

    public function init(): void {
        // ERROR: nullable readonly, ??= could attempt to write when foo is already null
        $this->foo ??= 'default';
    }
}

// @phan-read-only phpdoc property: treat same as native readonly
class PhpdocReadonly {
    /** @phan-read-only */
    public string $foo = 'x';

    public function init(): void {
        // ERROR: @phan-read-only (magic property write is always warned)
        $this->foo ??= 'default';
    }
}
