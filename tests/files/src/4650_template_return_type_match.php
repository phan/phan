<?php

/**
 * Test for issue #4650: Template types should be compatible with declared return types
 */

interface TestInterface
{
    /**
     * @template T of object
     * @param Closure():T $closure
     * @return T
     */
    public function test(Closure $closure): object;
}

class TestImpl implements TestInterface
{
    /**
     * @template T of object
     * @param Closure():T $closure
     * @return T
     */
    public function test(Closure $closure): object
    {
        return $closure();
    }
}

class GenericFactory {
    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     */
    public static function create(string $className): object
    {
        return new $className();
    }

    /**
     * @template T of \stdClass
     * @param class-string<T> $className
     * @return T
     */
    public static function createStdClass(string $className): \stdClass
    {
        return new $className();
    }

    /**
     * @template T
     * @param T $value
     * @return T
     */
    public static function identity(mixed $value): mixed
    {
        return $value;
    }
}

class ArrayProcessor {
    /**
     * @template T
     * @param list<T> $items
     * @return T|null
     */
    public function first(array $items): mixed
    {
        return $items[0] ?? null;
    }

    /**
     * @template TValue
     * @param array<int, TValue> $items
     * @return TValue|null
     */
    public function firstValue(array $items): mixed
    {
        foreach ($items as $value) {
            return $value;
        }
        return null;
    }
}
