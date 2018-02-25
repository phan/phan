<?php

class C
{
    /** @return self */
    public static function f()
    {
        return new static;
    }
}

class D extends C { }
function g(int $p) {}
g((new D)->f());
