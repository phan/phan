<?php

namespace TemplateVarianceHooks;

/**
 * @template-covariant T
 */
class Box
{
    /** @var list<T> */
    private array $stored = [];

    /** @var list<T> */
    public array $byReference { &get => $this->stored; }

    /** @var list<T> */
    public array $virtual { get => $this->all(); }

    /** @var list<T> */
    public array $backed { get => $this->backed; }

    /** @var list<T> */
    public private(set) array $privateSet = [];

    /** @var list<T> */
    public protected(set) array $protectedSet = [];

    /** @return list<T> */
    public function all(): array
    {
        return $this->stored;
    }
}

/**
 * @template-covariant T
 */
interface Readable
{
    /** @var T */
    public mixed $value { get; }

    /** @var T */
    public mixed $both { get; set; }
}

/**
 * A hook or the backing storage that is not redeclared is inherited.
 *
 * @template T
 */
class HookBase
{
    /** @var T */
    protected mixed $stored = null;

    /** @var T */
    public mixed $both { get => $this->stored; set { $this->stored = $value; } }

    /** @var T */
    public mixed $plain = null;

    /** @var T */
    public mixed $getOnly { get => $this->stored; }
}

/**
 * @template-covariant T
 * @extends HookBase<T>
 */
class HookChild extends HookBase
{
    /** @var T this can be written with the set hook of the parent class */
    public mixed $both { get => $this->stored; }

    /** @var T this can be written to the backing storage of the parent class */
    public mixed $plain { get => $this->stored; }

    /** @var T this cannot be written */
    public mixed $getOnly { get => $this->stored; }
}
