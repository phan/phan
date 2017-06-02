<?php

class A298
{
    private function hidden()
    {
        return true;
    }

    public function __call($name, array $args)
    {
        if ($name == 'hidden') {
            return $this->hidden();
        }
    }
}

/**
 * @method bool hidden()
 */
class B298
{
    /**
     * @suppress PhanRedefineFunction
     */
    private function hidden()
    {
        return true;
    }

    public function __call($name, array $args)
    {
        if ($name == 'hidden') {
            return $this->hidden();
        }
    }
}

class C298
{
    protected function hidden()
    {
        return true;
    }

    public function __call($name, array $args)
    {
        if ($name == 'hidden') {
            return $this->hidden();
        }
    }
}

class D298 extends C298
{
}

class E298
{
    private static function hidden()
    {
        return true;
    }

    public static function __callStatic($name, array $args)
    {
        if ($name == 'hidden') {
            return self::hidden();
        }
    }
}

$a = new A298();
var_dump($a->hidden());

$b = new B298();
var_dump($b->hidden());

$c = new C298();
var_dump($c->hidden());

$d = new D298();
var_dump($d->hidden());

var_dump(E298::hidden());
