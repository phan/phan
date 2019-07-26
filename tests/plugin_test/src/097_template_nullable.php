<?php

namespace NS;

use stdClass;

/**
 * @template T
 */
class NullableTemplate
{
    /** @var T */
    public $x;

    /**
     * @param T $x
     */
    public function __construct($x)
    {
        $this->x = $x;
    }

    /**
     * @return ?T
     */
    public function getX()
    {
        if (rand() % 2 > 0) {
            return $this->x;
        }
        return false;
    }
}
echo (new NullableTemplate(new stdClass()))->getX();
