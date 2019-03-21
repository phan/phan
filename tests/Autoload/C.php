<?php

namespace Tests\Autoload;

/**
 * Class C
 */
class C
{
    // Loaded via B::BAR
    public const FOO = 123;
}
