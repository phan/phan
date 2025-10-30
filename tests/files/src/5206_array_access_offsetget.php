<?php

/**
 * Test that Phan analyzes array access using signature of ArrayAccess->offsetGet
 * Addresses issue #2679
 */

// Test 1: Basic offsetGet return type inference
class StringContainer implements ArrayAccess {
    /** @param string $key */
    public function offsetExists($key): bool {
        return true;
    }

    /**
     * @param string $key
     * @return stdClass
     */
    public function offsetGet($key): stdClass {
        return new stdClass();
    }

    public function offsetSet($key, $value): void {
        throw new RuntimeException();
    }

    public function offsetUnset($key): void {
        throw new RuntimeException();
    }
}

function test_return_type() {
    $container = new StringContainer();
    // @phan-suppress-next-line PhanUnusedVariable
    $value = $container['field'];
    // Should infer as stdClass, not mixed

    // This should warn - stdClass cannot be echoed
    echo $container['field'];

    // This should NOT warn - stdClass is compatible with object
    function expects_object(object $o): void {}
    expects_object($container['field']);

    // This should warn - stdClass is not compatible with string
    function expects_string(string $s): void {}
    expects_string($container['field']);
}

// Test 2: Key type validation
function test_key_type() {
    $container = new StringContainer();

    // This should warn - stdClass is not compatible with string key
    // @phan-suppress-next-line PhanUnusedVariable
    $val = $container[new stdClass()];

    // This should NOT warn - string is the expected key type
    // @phan-suppress-next-line PhanUnusedVariable
    $val2 = $container['valid_key'];

    // This should warn - int literal 42 doesn't strictly match string type
    // (In strict mode, int doesn't auto-cast to string for typed parameters)
    // @phan-suppress-next-line PhanUnusedVariable
    $val3 = $container[42];
}

// Test 3: ArrayAccess with templates should still use templates
/**
 * @template TKey
 * @template TValue
 * @implements ArrayAccess<TKey, TValue>
 */
class GenericContainer implements ArrayAccess {
    /** @param TKey $key */
    public function offsetExists($key): bool {
        return true;
    }

    /** @param TKey $key @return TValue */
    public function offsetGet($key): mixed {
        throw new RuntimeException();
    }

    /** @param TKey $key @param TValue $value */
    public function offsetSet($key, $value): void {}

    /** @param TKey $key */
    public function offsetUnset($key): void {}
}

/** @param GenericContainer<int, string> $container */
function test_template_preference(GenericContainer $container) {
    $value = $container[0];
    // Should infer as string from template, not mixed from return type

    // This should NOT warn - string is compatible with string
    expects_string($value);

    // This should warn - string is not compatible with object
    expects_object($value);
}

// Test 4: Missing return type should fall back to mixed
class UnTypedContainer implements ArrayAccess {
    public function offsetExists($offset): bool {
        return true;
    }

    public function offsetGet($offset) {
        return 'something';
    }

    public function offsetSet($offset, $value): void {}

    public function offsetUnset($offset): void {}
}

function test_untyped_fallback() {
    $container = new UnTypedContainer();
    // @phan-suppress-next-line PhanUnusedVariable
    $value = $container['key'];
    // Should be mixed since no type hint

    // No warnings expected - mixed can be anything
    expects_string($value);
    expects_object($value);
}

// Test 5: No offsetGet method should fall back to mixed
class MinimalArrayAccess implements ArrayAccess {
    public function offsetExists($offset): bool {
        return true;
    }

    public function offsetGet($offset): mixed {
        return null;
    }

    public function offsetSet($offset, $value): void {}

    public function offsetUnset($offset): void {}
}

function test_minimal() {
    $container = new MinimalArrayAccess();
    // @phan-suppress-next-line PhanUnusedVariable
    $value = $container['key'];
    // Should infer as mixed from offsetGet return type
}

// Test 6: PHPDoc-only return type (no native type hint)
class PhpDocOnlyContainer implements ArrayAccess {
    public function offsetExists($offset): bool {
        return true;
    }

    /**
     * PHPDoc-only, no native return type
     * @return stdClass
     */
    public function offsetGet($offset) {
        return new stdClass();
    }

    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
}

function test_phpdoc_only() {
    $container = new PhpDocOnlyContainer();
    // @phan-suppress-next-line PhanUnusedVariable
    $value = $container['key'];
    // Should infer as stdClass from PHPDoc, but WITHOUT real type marker
    // (compare with Test 1 which has native return type)
}
