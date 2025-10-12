<?php

/**
 * Test for issue #4599: False positive PhanAccessReadOnlyProperty with isset()
 */

/**
 * @phan-side-effect-free
 */
class SideEffectFreeClass {
    protected array $values = [];

    // isset() should not trigger read-only warning
    public function get($key) {
        if (!isset($this->values[$key])) {
            throw new InvalidArgumentException();
        }
        return $this->values[$key];
    }

    // array_key_exists() should not trigger read-only warning
    public function has($key): bool {
        return array_key_exists($key, $this->values);
    }

    // Nested dimensions: isset($this->values['a']['b'])
    public function getNestedValue($key1, $key2) {
        if (!isset($this->values[$key1][$key2])) {
            throw new InvalidArgumentException();
        }
        return $this->values[$key1][$key2];
    }

    // Type checks like is_array() should not trigger warning
    public function checkType($key): bool {
        return is_array($this->values[$key]);
    }

    // Actual assignment SHOULD trigger warning
    public function shouldWarn($key, $value): void {
        $this->values[$key] = $value;
    }
}

class ClassWithReadonlyProperty {
    public function __construct(
        public readonly array $data = []
    ) {}

    // isset() on readonly property should not trigger warning
    public function has($key): bool {
        return isset($this->data[$key]);
    }

    // Actual assignment SHOULD trigger warning
    public function shouldWarn($key, $value): void {
        $this->data[$key] = $value;
    }
}

class ClassWithReadOnlyAnnotation {
    /**
     * @phan-read-only
     */
    protected array $config = [];

    // isset() should not trigger warning
    public function getConfig($key) {
        if (!isset($this->config[$key])) {
            return null;
        }
        return $this->config[$key];
    }

    // Actual assignment SHOULD trigger warning
    public function setConfig($key, $value): void {
        $this->config[$key] = $value;
    }
}
