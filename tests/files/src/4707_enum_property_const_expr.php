<?php

// Test enum property access in constant expressions (PHP 8.2+)

enum Status: string {
    case Pending = 'pending';
    case Active = 'active';
}

// Valid: Enum property in const
const C1 = Status::Pending->name;

// Valid: Enum property in static variable
function testStaticVar() {
    static $v = Status::Active->value;
}

// Valid: Enum property in parameter default
function testParamDefault($p = Status::Pending->value) {}

// Valid: Enum property in class property
class ValidClass {
    public string $prop = Status::Active->name;
}

// Valid: Enum property in class constant
class ValidClassConst {
    const C = Status::Pending->name;
}

// Nullsafe property access - valid with enum
const C2 = Status::Pending?->name;
