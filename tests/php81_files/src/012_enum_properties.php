<?php

namespace NS12;

enum UnitEnum {
    case FIRST;
}

enum BackedEnum: string {
    case ABC = 'abc';
}

var_dump(UnitEnum::FIRST->name);  // is valid
echo spl_object_hash(UnitEnum::FIRST->name); // should infer type 'string'
UnitEnum::FIRST->name = 'other';  // should warn
var_dump(UnitEnum::FIRST->value);  // should warn

echo spl_object_hash(BackedEnum::ABC->name); // should infer type 'string'
echo spl_object_hash(BackedEnum::ABC->value);  // should infer type 'string' or more specific
$a = BackedEnum::ABC;
unset($a->value);

// should warn about setting any property
$a->undeclaredProperty = 123;
unset($a->otherProperty);
