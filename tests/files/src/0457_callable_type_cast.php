<?php

/**
 * @param callable(int):void $fn
 */
function expects_cb_int_param(callable $fn) {
    $fn(1);
    echo $fn;
    $fn(new stdClass());  // should warn
    $fn();  // should warn
}

expects_cb_int_param(function(int $x) { echo $x; });
expects_cb_int_param(function(int $x, string $y='arg') { echo $x, $y; });
expects_cb_int_param(function(int $x, string $y) { echo $x, $y; });  // should warn
expects_cb_int_param(function(int $x = 2) { echo $x; return; });
expects_cb_int_param(function(int &$x) { $x = 2; });  // should warn

/**
 * @param callable(int=):void $fn
 */
function expects_cb_optional_int_param(callable $fn) {
    $fn(1);
    $fn(new stdClass());  // should warn
    $fn();
    $fn(null);  // should warn
}
expects_cb_optional_int_param(function(int $x) { echo $x; });  // should warn
expects_cb_optional_int_param(function(int $x = 2) { echo $x; });
expects_cb_optional_int_param(function(int $x = 2, int ...$rest) { echo $x, count($rest); });  // valid
expects_cb_optional_int_param(function(int $x, string $y) { echo $x, $y; });  // should warn
expects_cb_optional_int_param(function() { });  // should warn
expects_cb_optional_int_param(function(int &$x = 0) { $x = 2; });  // should warn


/**
 * Should warn
 * @param callable():int $fn
 */
function expects_cb_int_return(callable $fn) : stdClass {
    $fn(2);  // should warn
    return $fn();  // should warn, int can't cast to stdClass
}

expects_cb_int_return(function(...$args) : int { return count($args); } ); // should not warn
expects_cb_int_return(function(int $optional = 2) : int { return $optional; } ); // should not warn
expects_cb_int_return(function() : int { return 0; } ); // valid
expects_cb_int_return(function() : array { return []; } ); // should warn
expects_cb_int_return(function(array $args) : int { return count($args); } ); // should warn

/**
 * @param callable():void $fn
 */
function expects_cb_void(Closure $fn) {
    $result = $fn();  // should warn
    expects_cb_int_param($fn); // should warn
    expects_cb_int_return($fn); // should warn
    if (rand() % 2 > 0) {
        expects_cb_void($fn);
    }
}

expects_cb_void(function() { echo "invoked\n"; });  // valid
expects_cb_void(function() : void { echo "invoked\n"; });  // valid
expects_cb_void(function($extra) : void { echo "invoked $extra\n"; });  // invalid
expects_cb_void(function() : int { echo "invoked\n"; return 2; });  // invalid
expects_cb_void(function(int $extra) { echo "invoked $extra\n"; });  // invalid

/**
 * @param callable(string...):void $fn
 */
function expects_cb_void_variadic(callable $fn) {
    $fn('arg1', 'arg2');  // valid. TODO: Warn for third arg being non-string
    $fn();  // valid
    $fn(['arg']);  // should warn
}
expects_cb_void_variadic(function(string ...$args) { var_export($args); });
expects_cb_void_variadic(function(int ...$args) { var_export($args); });  // should warn
expects_cb_void_variadic(function(...$args) { var_export($args); });
expects_cb_void_variadic(function(string $arg) { var_export($arg); });  // should warn
expects_cb_void_variadic('strlen');  // should warn
expects_cb_void_variadic(['Closure', 'bind']);  // should warn
// Sanity check on type casting rules
function expects_regular_callable(callable $fn) {}
expects_regular_callable('strlen');
expects_cb_void_variadic(['Closure', 'bind']);
