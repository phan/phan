<?php

namespace Tests\Autoload;

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
