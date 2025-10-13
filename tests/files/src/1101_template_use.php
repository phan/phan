<?php

/**
 * Test for @template-use support
 * Classes can specify template parameters for traits they use
 */

/**
 * @template T
 */
trait Repository
{
    /** @var T */
    private $entity;

    /**
     * @param T $entity
     */
    public function setEntity($entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @return T
     */
    public function getEntity()
    {
        return $this->entity;
    }
}

/**
 * Basic trait usage with concrete type
 * @use Repository<User>
 */
class UserService
{
    use Repository;

    public function processUser(): void
    {
        $entity = $this->getEntity();
        '@phan-debug-var $entity';
    }
}

/**
 * @template T
 */
trait Timestamped
{
    /** @var T */
    private $timestamp;

    /**
     * @param T $timestamp
     */
    public function setTimestamp($timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return T
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}

/**
 * Multiple traits with different template parameters
 * @use Repository<Article>
 * @use Timestamped<\DateTimeImmutable>
 */
class ArticleService
{
    use Repository;
    use Timestamped;

    public function process(): void
    {
        $entity = $this->getEntity();
        '@phan-debug-var $entity';

        $timestamp = $this->getTimestamp();
        '@phan-debug-var $timestamp';
    }
}

/**
 * Nested generic types
 * @use Repository<array<string, User>>
 */
class UserMapService
{
    use Repository;

    public function processMap(): void
    {
        $entity = $this->getEntity();
        '@phan-debug-var $entity';
    }
}

/**
 * Using @phan-use variant
 * @template T
 * @phan-use Repository<T>
 */
class GenericService
{
    use Repository;

    /**
     * @return T
     */
    public function get()
    {
        return $this->getEntity();
    }
}

// Helper classes
class User
{
    public string $name = '';
}

class Article
{
    public string $title = '';
}
