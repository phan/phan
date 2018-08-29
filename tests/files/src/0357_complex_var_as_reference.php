<?php

$testVariable = [
    'Test Value One',
];

$variableName = 'testVariable';
// (TODO: We know some possible values, use it)
each($$variableName);  // Analyzing $$variableName passed to a function expecting a reference should not crash.
list($key, $value) = each($$variableName);

printf("%s %s\n", $key, $value);
echo count($variableName);  // should warn.
