<?php

namespace Tests\Autoload\Complex;

/**
 * Class C
 */
class C
{
    // Loaded via B::BAR
    public const FOO = 123;
}
