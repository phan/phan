<?php

namespace NS12;

enum MyUnitEnum {
    case FIRST;
}

enum MyBackedEnum: string {
    case ABC = 'abc';
}

var_dump(MyUnitEnum::FIRST->name);  // is valid
echo spl_object_hash(MyUnitEnum::FIRST->name); // should infer type 'string'
MyUnitEnum::FIRST->name = 'other';  // should warn
var_dump(MyUnitEnum::FIRST->value);  // should warn

echo spl_object_hash(MyBackedEnum::ABC->name); // should infer type 'string'
echo spl_object_hash(MyBackedEnum::ABC->value);  // should infer type 'string' or more specific
$a = MyBackedEnum::ABC;
unset($a->value);

// should warn about setting any property
$a->undeclaredProperty = 123;
unset($a->otherProperty);

var_dump(MyUnitEnum::FIRST instanceof \UnitEnum);
var_dump(MyBackedEnum::ABC instanceof \BackedEnum);
