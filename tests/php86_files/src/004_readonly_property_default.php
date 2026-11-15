<?php

namespace NS86ReadonlyDefault;

// PHP 8.6: readonly properties can have a default value (https://wiki.php.net/rfc/readonly_property_defaults)

final class Example
{
    public readonly string $kind = 'default';
    public readonly int $id;
    public readonly array $tags = [];

    public function __construct(int $id, public readonly int $promoted = 0)
    {
        $this->id = $id;
        // Error: Cannot modify readonly property Example::$kind (the default value is the initialization)
        $this->kind = 'other';
    }

    public function __clone()
    {
        // Allowed: __clone() may reinitialize a readonly property once
        $this->kind = 'clone';
    }

    public function modify(): void
    {
        $this->tags = ['x'];
    }
}

readonly class Point
{
    public int $x = 0;
    public int $y;

    public function __construct(int $y)
    {
        $this->x = 1;
        $this->y = $y;
    }
}

final class Coalesce
{
    public readonly int $id = 1;
    public readonly ?int $nullable = null;
    public readonly ?int $nullable_with_default = 3;

    public function __construct()
    {
        // Does not assign: the default value is not null
        $this->id ??= 2;
        $this->nullable_with_default ??= 2;
        // Error: this assigns, because the default value is null
        $this->nullable ??= 2;
    }
}

echo (new Coalesce())->id, (new Coalesce())->nullable, (new Coalesce())->nullable_with_default;
$example = new Example(1);
echo $example->kind, $example->id, count($example->tags), $example->promoted;
$example->kind = 'outside';
$point = new Point(2);
echo $point->x + $point->y;
echo strlen($example->tags);
