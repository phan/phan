<?php

namespace Tests\Autoload\Complex;

/**
 * Class D
 */
class D implements InterfaceB
{
    /**
     * Echos "foo"
     */
    public function foo(): void
    {
        echo 'foo';
    }
}
