<?php

namespace Issue5647Php84;

// 1. PHP 8.4 added types to the constants of internal classes: an untyped constant cannot override DateTime::ATOM
// (PHP: "Type of ChildOfDateTime::ATOM must be compatible with DateTimeInterface::ATOM of type string").
// This is also reported when the PHP version running Phan is newer than the target version.
class ChildOfDateTime extends \DateTime {
    public const ATOM = 'custom';
}
var_dump(ChildOfDateTime::ATOM);

// 2. A constant with the type of the internal constant can override it.
class TypedChildOfDateTime extends \DateTime {
    public const string ATOM = 'custom';
}
var_dump(TypedChildOfDateTime::ATOM);
