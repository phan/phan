<?php

interface HasId
{
    public int $id { get; }
}

/**
 * @require-implements HasId
 */
trait ShowsId
{
    public function showId(): int
    {
        return $this->id;
    }
}

// The trait does not declare $id, so a readonly class can use it
final readonly class Entity implements HasId
{
    use ShowsId;

    public function __construct(public int $id) {}
}

echo (new Entity(1))->showId();
