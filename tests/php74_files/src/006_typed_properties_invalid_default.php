<?php

namespace TypedProperties;

class BadDefaultType {
    public ?string $goodNullable = null;
    public ?string $goodNullableString = \PHP_VERSION;
    public string $goodString = \PHP_VERSION;
    public string $badNullable = null;
    public string $invalidFalseable = false;
    public int $invalidInt = 'foo' . 'bar';
    public string $invalidArray = [];
    public \stdClass $invalidDefaultObject = [];
    public MissingClass $invalidClass;
}

class Subclass extends BadDefaultType {
}
