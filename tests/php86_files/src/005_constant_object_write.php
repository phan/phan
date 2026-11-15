<?php

namespace NS86ConstantObject;

// PHP 8.6: properties of objects referenced by constants can be written to
// (https://wiki.php.net/rfc/const_object_property_write)

const OBJ = new \stdClass();

final class Counter
{
    public int $count = 0;
    /** @var list<string> */
    public array $names = [];
}

const COUNTER = new Counter();

enum Setting
{
    case Debug;
    const DEFAULT = self::Debug;
}

OBJ->value = 123;
OBJ->total ??= 0;
unset(OBJ->value);

COUNTER->count = 1;
COUNTER->count += 2;
COUNTER->count++;
COUNTER->names[] = 'a';
unset(COUNTER->names[0]);
COUNTER->count = 'not an int';
COUNTER->undeclared = 1;

// Still an error: writing to a dimension of a constant
unset(OBJ['key']);

echo COUNTER->count, Setting::DEFAULT->name;
