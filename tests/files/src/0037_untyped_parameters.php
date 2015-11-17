<?php

function f($a, $b) : string {
    return "$a ~ $b";
}

print f(3, 'str') . "\n";
print f('str2', 42) . "\n";
