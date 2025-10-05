<?php

// Test for issue #5123: Child class overriding __call should not inherit @phan-pure from parent

class ParentWithPureCall {
    /**
     * @phan-pure
     */
    public function __call(string $name, array $args): int {
        return 42;
    }
}

class ChildOverridesWithSideEffects extends ParentWithPureCall {
    // No @phan-pure annotation - has side effects
    public function __call(string $name, array $args): int {
        file_put_contents('/tmp/log.txt', "Called: $name\n", FILE_APPEND);
        return 100;
    }
}

class ChildKeepsPure extends ParentWithPureCall {
    /**
     * @phan-pure
     */
    public function __call(string $name, array $args): int {
        return 999;
    }
}

function testChildWithSideEffects() {
    $child = new ChildOverridesWithSideEffects();
    $child->someMethod();  // Should NOT warn - child's __call has side effects
}

function testChildKeepsPure() {
    $child = new ChildKeepsPure();
    $child->someMethod();  // SHOULD warn - child explicitly marked as @phan-pure
}

function testParent() {
    $parent = new ParentWithPureCall();
    $parent->someMethod();  // SHOULD warn - parent is @phan-pure
}

// Test __callStatic as well
class ParentWithPureCallStatic {
    /**
     * @phan-pure
     */
    public static function __callStatic(string $name, array $args): int {
        return 42;
    }
}

class ChildOverridesCallStaticWithSideEffects extends ParentWithPureCallStatic {
    public static function __callStatic(string $name, array $args): int {
        file_put_contents('/tmp/log.txt', "Called: $name\n", FILE_APPEND);
        return 100;
    }
}

function testCallStaticChild() {
    ChildOverridesCallStaticWithSideEffects::someMethod();  // Should NOT warn
}

function testCallStaticParent() {
    ParentWithPureCallStatic::someMethod();  // SHOULD warn
}
