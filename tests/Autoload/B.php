<?php

namespace Tests\Autoload;

/**
 * Class B
 * Load A via inheritance
 */
class B extends A
{
    /** Load C via const */
    public const BAR = C::FOO;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load D via new
        parent::__construct(new D, static::BAR);
    }

    /**
     * This is a function
     * Load E via method type hint
     * Load D via docblock type hint
     * Load G via return type
     *
     * @param E $e Real parameter
     * @param F $f Doc parameter
     * @return G
     */
    public function foo(E $e, $f): G
    {
        return parent::foo($e, $f);
    }
}
