<?php

// Test 1: Trait with property in readonly class - should warn
trait TraitWithProperty {
    /** @phan-suppress-next-line PhanUnreferencedPublicProperty */
    public mixed $x;
}

/** @phan-suppress-next-line PhanUnreferencedClass */
readonly class ReadonlyClass {
    use TraitWithProperty;
}

// Test 2: Trait with readonly property in readonly class - OK, no warning
trait TraitWithReadonlyProperty {
    /** @phan-suppress-next-line PhanWriteOnlyPublicProperty */
    public readonly int $value;
}

/** @phan-suppress-next-line PhanUnreferencedClass */
readonly class ReadonlyClass2 {
    use TraitWithReadonlyProperty;

    public function __construct(int $value) {
        $this->value = $value;
    }
}

// Test 3: Trait with only methods in readonly class - OK, no warning
trait TraitWithMethods {
    /** @phan-suppress-next-line PhanUnreferencedPublicMethod, PhanEmptyPublicMethod, PhanPluginUseReturnValueNoopVoid */
    public function doSomething(): void {}
}

/** @phan-suppress-next-line PhanUnreferencedClass */
readonly class ReadonlyClass3 {
    use TraitWithMethods;
}

// Test 4: Multiple traits, one with non-readonly property - should warn
trait TraitA {
    /** @phan-suppress-next-line PhanUnreferencedPublicMethod, PhanEmptyPublicMethod, PhanPluginUseReturnValueNoopVoid */
    public function a(): void {}
}

trait TraitB {
    /** @phan-suppress-next-line PhanUnreferencedPublicProperty */
    public int $b;
}

/** @phan-suppress-next-line PhanUnreferencedClass */
readonly class ReadonlyClass4 {
    use TraitA, TraitB;
}

// Test 5: Interface with trait - should warn (not allowed in PHP at all)
trait InterfaceTrait {
    /** @phan-suppress-next-line PhanUnreferencedPublicMethod, PhanEmptyPublicMethod, PhanPluginUseReturnValueNoopVoid */
    public function method(): void {}
}

/** @phan-suppress-next-line PhanUnreferencedClass */
interface TestInterface {
    use InterfaceTrait;
}

// Test 6: Normal (non-readonly) class with trait with non-readonly property - OK
/** @phan-suppress-next-line PhanUnreferencedClass */
class NormalClass {
    use TraitWithProperty;
}
