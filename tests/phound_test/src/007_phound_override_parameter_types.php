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

class Fish implements Animal {
    public function speak(): string {
        return "blub";
    }
}

class Bird implements Animal {
    public function speak(): string {
        return "tweet";
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

    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function runWithScalar(): void {
        $factory = new Factory();
        // Passing a literal string instead of Fish::class
        $fish = $factory->create('OverrideParameterTypes\Fish');
        $fish->speak();
    }

    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function runWithNamedArg(): void {
        $factory = new Factory();
        // Passing via named argument
        $bird = $factory->create(class_name: Bird::class);
        $bird->speak();
    }

    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function runWithVariadic(): void {
        // Variadic parameter: argument types should widen the element type,
        // not get merged into the wrapped list<T> form.
        $this->processAnimals(new Dog(), new Cat());
    }

    /**
     * @param Animal ...$animals
     * @phan-suppress-next-line PhanUnreferencedPublicMethod
     */
    public function processAnimals(Animal ...$animals): void {
        foreach ($animals as $animal) {
            $animal->speak();
        }
    }

    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function runWithUnpack(): void {
        // Unpacked arguments (...) should be skipped entirely —
        // we can't reliably map them to parameter indices.
        // Uses Fish and Bird (not Dog/Cat) so we can detect if unpack
        // incorrectly widens the parameter type.
        $animals = [new Fish(), new Bird()];
        $this->processAnimals(...$animals);
    }

    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function runWithIncompatibleArg(): void {
        $factory = new Factory();
        // Passing an incompatible type (array instead of string) — should NOT
        // widen the parameter type, since this is a type error.
        /** @phan-suppress-next-line PhanTypeMismatchArgument,PhanTypeMismatchArgumentReal */
        $result = $factory->create([1, 2, 3]);
        $result->speak();
    }
}
