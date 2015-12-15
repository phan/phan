<?php declare(strict_types=1);
namespace Phan;

trait Memoize {

    /**
     * @var array
     * A map from key to memoized values
     */
    private $memoized_data = [];

    /**
     * After a clone is called on this object, clone our
     * deep objects.
     *
     * @return null
     */
    /*
    public function __clone() {
        $data = $this->memoized_data;
        $this->memoized_data = [];
        foreach ($data as $key => $value) {
            $this->memoized_data[$key] =
                is_object($value) ? clone($value) : $value;
        }
    }
    */

    /**
     * Memoize the result of $fn(), saving the result
     * with key $key.
     *
     * @param string $key
     * The key to use for storing the result of the
     * computation.
     *
     * @param \Closure $fn
     * A function to compute only once for the given
     * $key.
     *
     * @return mixed
     * The result of the given computation is returned
     */
    protected function memoize(string $key, \Closure $fn) {
        if (!array_key_exists($key, $this->memoized_data)) {
            $this->memoized_data[$key] = $fn();
        }

        return $this->memoized_data[$key];
    }

    /**
     * Memoize the result of $fn(), saving the result
     * with key $key.
     *
     * @param string $key
     * The key to use for storing the result of the
     * computation.
     *
     * @param Closure $fn
     * A function to compute only once for the given
     * $key.
     *
     * @return mixed
     * The result of the given computation is returned
     */
    protected static function memoizeStatic(string $key, \Closure $fn) {
        static $memoized_data = [];

        if (!array_key_exists($key, $memoized_data)) {
            $memoized_data[$key] = $fn();
        }

        return $memoized_data[$key];
    }

    /**
     * Delete all memoized data
     *
     * @return null
     */
    protected function memoizeFlushAll() {
        $this->memoized_data = [];
    }
}
