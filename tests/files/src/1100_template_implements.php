<?php

/**
 * Test for @template-implements support
 * Classes can specify template parameters for implemented interfaces
 */

/**
 * @template T
 */
interface Repository
{
    /**
     * @param int $id
     * @return T
     */
    public function find(int $id);

    /**
     * @param T $entity
     * @return void
     */
    public function save($entity): void;
}

/**
 * Basic implementation with concrete type
 * @implements Repository<User>
 */
class UserRepository implements Repository
{
    public function find(int $id): User
    {
        return new User();
    }

    public function save($entity): void
    {
        // Save user
    }
}

/**
 * Multiple interfaces with template parameters
 * @template T
 * @implements Iterator<int, T>
 * @implements Countable
 */
class UserCollection implements Iterator, Countable
{
    /** @var list<T> */
    private $items = [];

    public function current(): mixed
    {
        return current($this->items);
    }

    public function next(): void
    {
        next($this->items);
    }

    public function key(): mixed
    {
        return key($this->items);
    }

    public function valid(): bool
    {
        return key($this->items) !== null;
    }

    public function rewind(): void
    {
        reset($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}

/**
 * Nested generic types
 * @implements Repository<array<string, User>>
 */
class UserMapRepository implements Repository
{
    /**
     * @param int $id
     * @return array<string, User>
     */
    public function find(int $id): array
    {
        return [];
    }

    /**
     * @param array<string, User> $entity
     */
    public function save($entity): void
    {
        // Save
    }
}

/**
 * Using @phan-implements variant
 * @template T
 * @phan-implements Repository<T>
 */
class GenericRepository implements Repository
{
    /**
     * @param int $id
     * @return T
     */
    public function find(int $id)
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * @param T $entity
     */
    public function save($entity): void
    {
        // Save
    }
}

// Helper class for tests
class User
{
    public string $name = '';
}
