<?php

namespace Tests\Autoload\Simple;

/**
 * Class A
 */
class A extends B
{
    /** @var string */
    private $a;

    public function __construct(bool $a)
    {
        if ($a) {
            echo 'A';
        }
    }
}
