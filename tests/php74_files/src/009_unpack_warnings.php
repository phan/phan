<?php

$x = 'a string';
var_dump(2, ...$x);
$y = [2, ...$x];
$z = [...'', ...0, ...1, ...null];
$w = [...$undef];

$validLiteral = [...[2 => 3]];
echo intdiv($validLiteral, 2);
$invalidLiteral = [...['dos' => 2]];

/**
 * @param array<string,object> $x
 * @return array<int,false>
 */
function expects_stringmap(array $x) {
    return array(
        $x,  // should warn
        ...$x,
    );
}
expects_stringmap(['key' => new stdClass()]);
