<?php

// Phan supports php 7.4's covariant return types
// when the configured 'closest_minimum_target_php_version' is '7.4' or newer.
// (it looks for the version range in composer.json as a fallback)
namespace NS31;

declare(strict_types=1);


class SubClass {

}

class BaseClass extends SubClass {

}

interface FooFactoryInterface {
    public function build(BaseClass $o): SubClass;
}

interface BarFactorInterface extends FooFactoryInterface {
    public function build(SubClass $o): BaseClass;
}

class BarFactory implements FooFactoryInterface {
    public function build(SubClass $o): BaseClass {
        return new BaseClass();
    }
}

if (is_a((new BarFactory)->build(new SubClass()), BaseClass::class)) {
    echo "\nSUCCESS\n";
} else {
    echo "\nFAIL\n";
}
