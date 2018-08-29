<?php

namespace Tests\Autoload;

class TestException extends ParentException
{
    public function __construct($a = '')
    {
        $foo = ParentException::FOO;
    }
}
