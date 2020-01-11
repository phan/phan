<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * A cache from string keys to object instances
 *
 * @template T
 */
interface Cache
{
    /**
     * Retrieve a copy of the value from the cache, or return null
     *
     * @return ?T
     */
    public function getIfExists(string $key);

    /**
     * Save a copy of $value to the cache.
     *
     * @param T $value
     * @return bool true if successfully saved
     */
    public function save(string $key, $value): bool;
}
