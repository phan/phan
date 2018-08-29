<?php

namespace Tests\Autoload;

class ParentException extends \Exception
{
    const FOO = 123;

    public function __construct($a = '')
    {
        parent::__construct('', 0);
    }
}
