<?php

namespace NS22;
use BackedEnum;
use UnitEnum;

enum Backed: int {
    case X = 123;
    case Y = 456;
}

enum Unit {
    case X;
    case Y;
}
var_dump(Backed::X instanceof BackedEnum);
var_dump(Backed::Y instanceof UnitEnum);

var_dump(Unit::X instanceof BackedEnum);
var_dump(Unit::Y instanceof UnitEnum);
$backed_cases = Backed::cases();
$unit_cases = Unit::cases();
'@phan-debug-var $unit_cases, $backed_cases';

Unit::from('not a method');
Unit::tryFrom('not a method');

Backed::from('not a method');
Backed::tryFrom('not a method');
$arg = Backed::from(123);
'@phan-debug-var $arg';
