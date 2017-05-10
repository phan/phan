<?php

class A291
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
class B291
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

class C291
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

class D291 extends C291
{
}

$a = new A291();
var_dump($a->hidden());

$b = new B291();
var_dump($b->hidden());

$c = new C291();
var_dump($c->hidden());

$d = new D291();
var_dump($d->hidden());
