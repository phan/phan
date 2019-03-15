<?php

// The expressions are passed to strlen so that the error message indicates the inferred literal in the union type
function testString(string $s, int $i, float $f) {
    // This will print "1" and "-22", and is likely to be a bug. Phan should warn.
    echo strlen(+"1GB");
    echo strlen(-"22MB");
    // This won't work as expected for hex (prints 0), Phan should warn
    echo strlen(+"0x12");
    echo strlen(-"0x12");
    // This will work as expected, Phan does not warn.
    echo strlen(+"123");
    echo strlen(-"123");
    // Currently, Phan intentionally doesn't warn about strings with no known values.
    echo +$s;
    echo -$s;
    echo +$i;
    echo -$i;
    echo -$f;
    echo -$f;
    echo strlen(+"1.2");  // infers float
    echo strlen(-"1.3");  // infers float
}
