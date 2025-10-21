<?php
// In PHP 8.0, false and null became valid for parameter types, property types, and return types.
// (At compile time(not parse time), they're only allowed in combination with at least one type that isn't false/null)
function (\stdClass|false|null|namespace\ArrayObject $arg) : false { };
