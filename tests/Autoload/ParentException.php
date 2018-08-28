<?php

namespace Tests\Autoload;

class ParentException extends \Exception
{
    public function __construct($a = '')
    {
        parent::__construct('', 0);
    }
}
