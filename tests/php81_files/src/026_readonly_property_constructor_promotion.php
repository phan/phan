<?php

/**
 * Test case for issue #5063 - readonly property promotion assignments in constructor
 * should not trigger PhanAccessReadOnlyProperty warnings
 */
class DemoPromotion {
    public function __construct(private readonly string $input) {
        $this->input = $input; // Should NOT trigger PhanAccessReadOnlyProperty
    }

    public function modify(): string {
        $this->input .= 'test'; // SHOULD trigger PhanAccessReadOnlyProperty
        return $this->input;
    }
}

class DemoRegular {
    private readonly string $input;

    public function __construct(string $input) {
        $this->input = $input; // Should NOT trigger PhanAccessReadOnlyProperty
    }

    public function modify(): void {
        $this->input = 'changed'; // SHOULD trigger PhanAccessReadOnlyProperty
    }
}

class Parent_ {
    protected readonly string $data;

    public function __construct(string $data) {
        $this->data = $data; // Should NOT trigger PhanAccessReadOnlyProperty
    }
}

class Child extends Parent_ {
    public function __construct(string $data) {
        $this->data = $data; // Should NOT trigger PhanAccessReadOnlyProperty (subclass constructor)
    }

    public function modify(): void {
        $this->data = 'changed'; // SHOULD trigger PhanAccessReadOnlyProperty
    }
}