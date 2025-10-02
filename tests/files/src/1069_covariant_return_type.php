<?php declare(strict_types=1);

// Phan supports covariant return types

namespace NS1069;

class BaseClass {

}

class SubClass extends BaseClass {

}

interface FooFactoryInterface {
    public function build(SubClass $o): BaseClass;
}

interface BarFactorInterface extends FooFactoryInterface {
    public function build(BaseClass $o): SubClass;
}

class BarFactory implements FooFactoryInterface {
    public function build(BaseClass $o): SubClass {
        return new SubClass();
    }
}

if (is_a((new BarFactory)->build(new BaseClass()), SubClass::class)) {
    echo "\nSUCCESS\n";
} else {
    echo "\nFAIL\n";
}
