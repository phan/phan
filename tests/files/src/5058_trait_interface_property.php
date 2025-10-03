<?php

declare(strict_types=1);

interface Issue5001FooInterface
{
    public function doFoo(): void;
}

interface Issue5001BazInterface
{
    public function checkFoo(Issue5001FooInterface $foo): bool;
    public function doBaz(Issue5001FooInterface $foo): void;
}

trait Issue5001Trait
{
    /** @var list<Issue5001BazInterface> */
    private array $panels = [];

    public function doFoo(Issue5001BazInterface $panel): void
    {
        if ($this instanceof Issue5001FooInterface && $panel->checkFoo($this)) {
            throw new \InvalidArgumentException('Foo.');
        }
        $this->panels[] = $panel;
    }
}

class Issue5001Dummy
{
    use Issue5001Trait;
}

(new Issue5001Dummy())->doFoo(new class implements Issue5001BazInterface {
    public function checkFoo(Issue5001FooInterface $foo): bool
    {
        return false;
    }

    public function doBaz(Issue5001FooInterface $foo): void
    {
    }
});
