<?php

/**
 * Test for issue #5441: False positive PhanRedundantCondition/PhanCoalescingNeverNull
 * when using isset() or ?? on ArrayAccess objects with non-nullable offsetGet().
 */

/**
 * @implements \ArrayAccess<string, string>
 */
class MyArrayAccess5909 implements \ArrayAccess {
    /** @var array<string, string> */
    private array $data = [];

    public function offsetExists(mixed $offset): bool {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): string {
        return $this->data[$offset] ?? '';
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->data[$offset]);
    }

    // Should NOT warn - $this[$key] uses ArrayAccess via offsetExists()
    public function getOrDefault(string $key): string {
        return $this[$key] ?? 'default';
    }

    // Should NOT warn - $this[$key] isset check calls offsetExists()
    public function has(string $key): bool {
        return isset($this[$key]);
    }
}

function test_isset_array_access(MyArrayAccess5909 $obj, string $key): void {
    // Should NOT warn - offsetExists() can return false
    if (isset($obj[$key])) {
        echo $obj[$key];
    }
}

function test_coalesce_array_access(MyArrayAccess5909 $obj, string $key): void {
    // Should NOT warn - offsetExists() can return false
    $val = $obj[$key] ?? 'default';
    echo $val;
}

function test_isset_in_loop(MyArrayAccess5909 $obj): void {
    $keys = ['a', 'b', 'c'];
    // Should NOT warn with PhanRedundantConditionInLoop
    foreach ($keys as $key) {
        if (isset($obj[$key])) {
            echo $obj[$key];
        }
    }
}

