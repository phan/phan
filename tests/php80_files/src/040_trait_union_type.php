<?php

namespace NS40;

class a
{

}
class b
{

}
class c
{
    public function aOrB(a | b $foo): void
    {
        var_export($foo);
    }
    public function aOrNull(a | null $foo): void
    {
        var_export($foo);
    }

    public function questionA(?a $foo): void
    {
        var_export($foo);
    }
}
trait d
{
    public function aOrB(a | b $foo): void
    {
        var_export($foo);
    }

    public function aOrNull(a | null $foo): void
    {
        var_export($foo);
    }

    public function questionA(?a $foo): void
    {
        var_export($foo);
    }
}

class e {
    use d;
}
(new e())->questionA(null);
(new e())->questionA(new \stdClass());
(new e())->aOrNull(null);
(new e())->aOrB(null);
