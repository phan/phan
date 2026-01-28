<?php

/**
 * Test case for GitHub issue #5424
 * array_map with ternary expressions should not produce false positives
 *
 * @see https://github.com/phan/phan/issues/5424
 * @phan-file-suppress PhanRedundantConditionInGlobalScope
 * @phan-file-suppress PhanImpossibleConditionInGlobalScope
 */

$var = 'test';
/** @var bool $single */
$single = true;

// Original issue case - should NOT warn
$mails = array_map(trim(...), ($single ?
    [$var] :
    explode(',', strtr($var, [';' => ',']))
));

// First-class callable with ternary - should NOT warn
$r1 = array_map(trim(...), ($single ? [$var] : explode(',', $var)));

// String callable with ternary - should NOT warn
$r2 = array_map('trim', ($single ? [$var] : explode(',', $var)));

// strtoupper with ternary - should NOT warn
$r3 = array_map(strtoupper(...), ($single ? ['hello'] : ['world', 'test']));

// Ternary with explode on both sides - should NOT warn
$r4 = array_map('trim', ($single ? explode(',', 'a,b') : explode(';', 'c;d')));

// Arrow function with ternary array - should NOT warn
$r5 = array_map(fn($s) => trim($s), ($single ? [$var] : explode(',', $var)));

// These SHOULD warn - real type errors
// int is not compatible with trim's string parameter
$r6 = array_map(trim(...), ($single ? [123] : [456]));

// Mixed types, int in false branch not compatible with trim
$r7 = array_map(trim(...), ($single ? ['hello'] : [123]));
