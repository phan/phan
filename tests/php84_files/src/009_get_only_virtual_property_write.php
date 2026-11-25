<?php

namespace GetOnlyWrite;

class Circle
{
    public float $radius = 1.0;

    public float $area {
        get => 3.14159 * $this->radius ** 2;
    }

    public ?string $label {
        get => null;
    }

    // Backed: the hook reads the property itself, so it can be written.
    public int $backed {
        get => $this->backed + 1;
    }

    public array $list {
        &get => $this->store;
    }

    private array $store = [];

    public int $both {
        get => 1;
        set {
        }
    }

    public function __construct()
    {
        $this->area = 2.0; // also not allowed in the class itself
    }
}

class GetOnlyChild extends Circle
{
    public int $both {
        get => 2;
    }
}

function writes(Circle $c, GetOnlyChild $child): void
{
    $c->area = 1.0;
    $c->area += 1.0;
    $c->area++;
    [$c->area] = [1.0];
    ['a' => $c->area] = ['a' => 1.0];
    foreach ([1.0] as $c->area) {
    }
    $c->area ??= 1.0; // never assigns: the type is not nullable
    $c->label ??= 'x';
    $c->list = [1];
    $c->list[] = 1; // the get hook returns a reference
    $c->backed = 1;
    $c->both = 1;
    $child->both = 1; // the set hook is inherited
    var_dump(isset($c->area));
}

// A subclass makes the property writable, so the write is not reported for the base class.
class Base
{
    public int $n {
        get => 1;
    }
}

class Middle extends Base
{
}

class Writable extends Middle
{
    public int $n {
        get => 2;
        set {
        }
    }
}

interface HasN
{
    public int $n { get; }
}

abstract class RequiresN
{
    abstract public int $n { get; }
}

trait VirtualN
{
    public int $n {
        get => 1;
    }
}

class UsesVirtualN
{
    use VirtualN;
}

class Plain
{
    public int $n = 0;
}

class UsesVirtualNOverPlain extends Plain
{
    use VirtualN;
}

function writesOther(Base $b, Middle $m, HasN $i, RequiresN $r, UsesVirtualN $u, UsesVirtualNOverPlain $p): void
{
    $b->n = 1;
    $m->n = 1;
    $i->n = 1; // the class of the object can declare a set hook
    $r->n = 1;
    $u->n = 1;
    $p->n = 1; // the backing storage of Plain is inherited
}
