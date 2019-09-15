<?php declare(strict_types=1);

namespace Phan;

use Closure;

/**
 * A utility trait to memoize (cache) the result of instance methods and static methods.
 */
trait Memoize
{
    use Profile;

    /**
     * @var array<string,mixed>
     * A map from key to memoized values
     * @internal
     */
    protected $memoized_data = [];

    /**
     * Memoize the result of $fn(), saving the result
     * with key $key.
     *
     * @template T
     *
     * @param string $key
     * The key to use for storing the result of the
     * computation.
     *
     * @param Closure():T $fn
     * A function to compute only once for the given
     * $key.
     *
     * @return T
     * The result of the given computation is returned
     */
    protected function memoize(string $key, Closure $fn)
    {
        if (!\array_key_exists($key, $this->memoized_data)) {
            $this->memoized_data[$key] = $fn();
        }

        return $this->memoized_data[$key];
    }

    /**
     * @param string $key
     * A unique key to test to see if it's been seen before
     *
     * @return bool
     * True if this is the first time this function has been
     * called on this class with this key.
     */
    protected function isFirstExecution(string $key) : bool
    {
        if (!\array_key_exists($key, $this->memoized_data)) {
            $this->memoized_data[$key] = true;
            return true;
        }

        return false;
    }

    /**
     * Memoize the result of $fn(), saving the result
     * with key $key.
     * (cached statically)
     *
     * @template T
     *
     * @param string $key
     * The key to use for storing the result of the
     * computation.
     *
     * @param Closure():T $fn
     * A function to compute only once for the given
     * $key.
     *
     * @return T
     * The result of the given computation is returned
     */
    protected static function memoizeStatic(string $key, Closure $fn)
    {
        static $memoized_data = [];

        if (!\array_key_exists($key, $memoized_data)) {
            $memoized_data[$key] = $fn();
        }

        return $memoized_data[$key];
    }

    /**
     * Delete all memoized data
     */
    protected function memoizeFlushAll() : void
    {
        $this->memoized_data = [];
    }
}
