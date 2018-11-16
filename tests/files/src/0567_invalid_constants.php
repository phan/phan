<?php
/**
 * Tests for https://github.com/phan/phan/issues/2128
 *
 * Note that the order in which Phan parses define() still has edge cases.
 * Putting files with constants first in `directory_list` is recommended.
 */
declare(strict_types=1);

function exampleConstantDefs() {
    $str2und = 'a';
    define('cons2128', $str2und);
    unset($str2und);
    define('cons2128Error', $undError);  // should warn, this is undefined
    define('cons2128Error2');
    echo strlen(cons2128Error);
    echo cons2128Error2;  // Should warn - This constant does not exist - the declaration will fail with only 1 arg
    define($undefVar);  // Should emit undefined variable warnings and PhanParamTooFewInternal
    $invalid = '';
    define($invalid, 'x');  // Should emit PhanInvalidConstantFQSEN
}

exampleConstantDefs();

echo intdiv(cons2128, 2);  // should warn - cons2128 should have type 'string'
var_dump(strpos(cons2128, 'b'));  // should not warn

$str2int = 'a';
define('cons21282', $str2int);
$str2int = 0;

var_dump(strpos(cons21282, 'b'));
var_dump(intdiv(cons21282, 4));

$cons21283 = 'cons21283';
define($cons21283, count($_SERVER));
echo cons21283;  // should not warn
echo strlen(cons21283);  // should warn
