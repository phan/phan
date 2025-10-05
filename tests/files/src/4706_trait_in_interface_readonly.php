<?php

// Test 1: Trait with property in readonly class - should warn
trait TraitWithProperty {
    public mixed $x;
}

readonly class ReadonlyClass {
    use TraitWithProperty;
}

// Test 2: Trait with readonly property in readonly class - OK, no warning
trait TraitWithReadonlyProperty {
    public readonly int $value;
}

readonly class ReadonlyClass2 {
    use TraitWithReadonlyProperty;

    public function __construct(int $value) {
        $this->value = $value;
    }
}

// Test 3: Trait with only methods in readonly class - OK, no warning
trait TraitWithMethods {
    public function doSomething(): void {}
}

readonly class ReadonlyClass3 {
    use TraitWithMethods;
}

// Test 4: Multiple traits, one with non-readonly property - should warn
trait TraitA {
    public function a(): void {}
}

trait TraitB {
    public int $b;
}

readonly class ReadonlyClass4 {
    use TraitA, TraitB;
}

// Test 5: Interface with trait - should warn (not allowed in PHP at all)
trait InterfaceTrait {
    public function method(): void {}
}

interface TestInterface {
    use InterfaceTrait;
}

// Test 6: Normal (non-readonly) class with trait with non-readonly property - OK
class NormalClass {
    use TraitWithProperty;
}
