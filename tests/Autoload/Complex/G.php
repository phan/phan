<?php

namespace Tests\Autoload\Complex;

/**
 * Class G
 */
class G
{
    /** @var E */
    private $e;

    /** @var F */
    private $f;

    /**
     * @param E $e
     * @param F $f
     */
    public function __construct(E $e, F $f)
    {
        $this->e = $e;
        $this->f = $f;

        if ($this->e && $this->f) {
            echo 'test';
        }
    }
}
