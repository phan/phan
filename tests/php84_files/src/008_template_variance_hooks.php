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
