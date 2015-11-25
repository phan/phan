<?php

$closure_int = function(int $i) : int { return $i; };
$closure_string = function(string $s) : string { return $s; };

print $closure_int("string") . "\n";
print $closure_string(42) . "\n";
