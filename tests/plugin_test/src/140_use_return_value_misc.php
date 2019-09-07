<?php
// should warn
preg_match('/x/', 'executable');
// should not warn
preg_match('/x/', 'executable', $matches);
var_export($matches);
// Should only warn about unused `*_exists` calls when $autoload is false
class_exists('TestClass');
class_exists('TestClass', true);
class_exists('TestClass', false);
interface_exists('TestInterface', true);
interface_exists('TestInterface', false);
trait_exists('TestTrait', true);
trait_exists('TestTrait', false);
// UseReturnValuePlugin should warn because the return value of intdiv should be used.
call_user_func('intdiv', 1, 2);
call_user_func_array('intdiv', [1, 2]);
$c = Closure::fromCallable('intdiv');
call_user_func($c, 1, 2);
call_user_func_array($c, [1, 2]);
// But should not warn here because the result of var_dump doesn't need to be used.
$c2 = Closure::fromCallable('var_dump');
call_user_func($c2, 1, 2);
call_user_func_array($c2, [1, 2]);
call_user_func('var_dump', 1, 2);
call_user_func_array('var_dump', [1, 2]);

// should warn
preg_match_all('/x/', 'executable');
// should not warn
preg_match_all('/x/', 'executable', $matches);
var_export($matches, 0);
var_export($matches, 1);
