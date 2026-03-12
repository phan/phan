<?php

namespace OverrideParameterTypes;

// Test that override_parameter_types propagates caller argument types
// to method parameters, enabling concrete type resolution through factory methods.
// Without override_parameter_types, the create() method's $class_name parameter
// is just 'string', so new $class_name() returns 'object' and the concrete
// callsites (Dog::speak, Cat::speak) are not found.

interface Animal {
    public function speak(): string;
}

class Dog implements Animal {
    public function speak(): string {
        return "woof";
    }
}

class Cat implements Animal {
    public function speak(): string {
        return "meow";
    }
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class Factory {
    /**
     * @return Animal
     */
    public function create(string $class_name): Animal {
        return new $class_name(); // @phan-suppress-current-line PhanTypeExpectedObjectOrClassName
    }
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class Caller {
    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function run(): void {
        $factory = new Factory();
        $dog = $factory->create(Dog::class);
        $dog->speak();
        $cat = $factory->create(Cat::class);
        $cat->speak();
    }
}
