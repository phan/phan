<?php

namespace NS18;

use ArrayObject;
use Attribute;

#[Attribute]
class ExampleAttribute {
    public function __construct(
        public ArrayObject $values = new ArrayObject(['first'])
    ) {}
}

const Values = new ArrayObject();
static $values = new ArrayObject();
static $e = new ExampleAttribute($values);

#[ExampleAttribute(new ArrayObject(array: [1]))]
class Other {
}

