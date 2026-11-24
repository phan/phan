<?php

namespace ImmutableTag5977;

/**
 * @immutable
 */
final class Point
{
    public int $x;

    public function __construct(int $x)
    {
        $this->x = $x;
    }

    public function move(): void
    {
        $this->x++;
    }
}

/** @psalm-immutable */
final class PsalmPoint
{
    public int $x = 1;
}

/**
 * A covariant template can be used in a property that cannot be written.
 *
 * @template-covariant T
 * @immutable
 */
final class Holder
{
    /** @var T */
    public $value;

    /** @param T $value */
    public function __construct($value)
    {
        $this->value = $value;
    }
}

function mutate(Point $point, PsalmPoint $psalm_point): void
{
    $point->x = 2;
    $psalm_point->x = 3;
    echo $point->x, $psalm_point->x;
}

/** @immutable */
function notAClass(): void
{
}
