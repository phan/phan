<?php
error_reporting(E_ALL | E_STRICT);

class Test
{
    public function baz()
    {
        printf("baz = \n");
    }

    public static function staticMethod()
    {
        static::baz();
    }
}

Test::staticMethod();
