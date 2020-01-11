<?php

declare(strict_types=1);

namespace Phan\Library;

use Exception;

/**
 * This represents the absence of a value in an Option.
 *
 * @inherits Option<null>
 * @phan-pure
 */
final class None extends Option
{
    /**
     * Get a new instance of nothing
     * @deprecated
     */
    public function __construct()
    {
    }

    /** @var None */
    private static $instance;

    /**
     * Fetches the shared instance of None.
     */
    public static function instance(): None
    {
        return self::$instance;
    }

    public function isDefined(): bool
    {
        return false;
    }

    /**
     * @template E
     * @param E $else
     * @return E
     * @suppress PhanParamSignatureMismatch
     */
    public function getOrElse($else)
    {
        return $else;
    }

    /**
     * @return null
     * @throws Exception to indicate that get() was called without checking for a value.
     */
    public function get()
    {
        throw new Exception("Cannot call get on None");
    }

    /**
     * @return string
     * A string representation of this object
     */
    public function __tostring(): string
    {
        return 'None()';
    }

    /**
     * Called automatically to instantiate shared instance
     * @internal
     * @suppress PhanDeprecatedFunction
     */
    public static function init(): void
    {
        self::$instance = new None();
    }
}
None::init();
