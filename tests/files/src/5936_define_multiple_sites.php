<?php

// Test for https://github.com/phan/phan/issues/5542
// Constants declared with define() keep their real type (widened to the non-literal type),
// and all define() calls for the same name are unioned.

define('DEFINITE_STRING5936', random_bytes(3));
$from_constant_fn = constant('DEFINITE_STRING5936');
'@phan-debug-var $from_constant_fn';

if ($_ENV['USE_INT']) {
    define('MULTI_SITE5936', 3);
} else {
    define('MULTI_SITE5936', true);
}
$from_multi_site = constant('MULTI_SITE5936');
'@phan-debug-var $from_multi_site';

define('DEBUG_MODE5936', false);
define('AST_VERSION5936', 120);

class HasVersion5936 {
    const VERSION = AST_VERSION5936;
}

function check5936(int $intVar, bool $boolVar, string $strVar) {
    if (DEFINITE_STRING5936 === $intVar) {  // should warn - string is never identical to int
        echo "impossible\n";
    }
    if (MULTI_SITE5936 === $boolVar) {  // should not warn - the else branch defines it as true
        echo "possible\n";
    }
    if (MULTI_SITE5936 === $strVar) {  // should warn - int|bool is never identical to string
        echo "impossible\n";
    }
    if (DEBUG_MODE5936) {  // should not warn - define() is used for configuration values
        echo "debug\n";
    }
    if (HasVersion5936::VERSION >= 120) {  // should not warn about a redundant comparison
        echo "new enough\n";
    }
    return intdiv(DEFINITE_STRING5936, 2);  // should warn (Real)
}

// Multiple define() calls on the same line are still distinct definitions.
if ($_ENV['A']) { define('ONE_LINE5936', 1); } elseif ($_ENV['B']) { define('ONE_LINE5936', 'x'); } else { define('ONE_LINE5936', 2.5); }
$from_one_line = constant('ONE_LINE5936');
'@phan-debug-var $from_one_line';
