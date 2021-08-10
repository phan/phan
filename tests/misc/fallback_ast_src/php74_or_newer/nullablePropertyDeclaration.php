<?php

class TestClass
{
    private ?float $val;

    public function __construct(?float $val)
    {
        $this->val = $val;
    }
}
