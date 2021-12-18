<?php

namespace Test974;

interface Foo {}
interface Foo2 {}

class Bar implements Foo {}

class Test
{
    /**
     * @var array<Foo>
     */
    public array $foos;
    /**
     * @var array<Foo2>
     */
    public array $foo2s;

    public Bar $bar;

    public function test()
    {
        if (!in_array($this->bar, $this->foos)) {
            return;
        }

        $this->somethingWithBar($this->bar);
        $type = $this->bar;
        '@phan-debug-var $type';

        if (!in_array($this->bar, $this->foo2s)) {
            return;
        }

        $this->somethingWithBar($this->bar);
        $type = $this->bar;
        '@phan-debug-var $type';
    }

    public function somethingWithBar(Bar $bar)
    {
        // Do something
    }
}
