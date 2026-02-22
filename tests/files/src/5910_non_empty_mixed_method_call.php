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
 * Exercise the crash: non-empty-mixed must appear before the class type
 * in the union so that findTypeMatchingCallback iterates over it first
 * when resolving the static return type.
 *
 * @param non-empty-mixed|Fluent5910 $val
 */
function test_method_on_non_empty_mixed($val): void {
    echo $val->doSomething()->getValue();
}
