<?php

/**
 * Test for issue #5442: InvalidFQSENException crash when calling a method
 * on a variable with type non-empty-mixed.
 *
 * Phan should not crash with InvalidFQSENException for '\non-empty-mixed'
 * when resolving static return types on method calls.
 */

class Fluent5910 {
    /** @return static */
    public function doSomething(): static {
        return $this;
    }

    public function getValue(): string {
        return 'value';
    }
}

/**
 * @param non-empty-array<Fluent5910> $items
 */
function test_non_empty_mixed_method_call(array $items): void {
    foreach ($items as $item) {
        echo $item->doSomething()->getValue();
    }
}

/**
 * @param non-empty-mixed $val
 */
function test_method_on_non_empty_mixed($val): void {
    if ($val instanceof Fluent5910) {
        echo $val->doSomething()->getValue();
    }
}
