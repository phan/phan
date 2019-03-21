<?php

namespace Tests\Autoload;

/**
 * Class A
 * Load interface via implements
 */
class A extends \Exception implements InterfaceA
{
    /**
     * @var D
     */
    private $d;

    /**
     * @param D $d
     */
    public function __construct(D $d, int $foo)
    {
        if ($foo > 0) {
            $this->d = $d;
        }
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
        $this->bar();

        return new G($e, $f);
    }

    /**
     * Load interface via instanceof
     * @return H
     */
    public function bar()
    {
        if ($this->d instanceof InterfaceB) {
            $this->d->foo();
        }

        return new H;
    }
}
