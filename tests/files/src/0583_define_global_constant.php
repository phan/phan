<?php
namespace test2207;
define('TEST', 1);
echo \TEST;  // should not warn
echo \test2207\TEST;
echo TEST;  // should not warn
echo namespace\TEST;  // should warn

$x = 'TEST2';
define($x, []);
echo TEST2;  // should not warn
echo namespace\TEST2;  // should not warn

define('\\', 'empty');
define('foo\\\\TEST3', 'empty');  // should warn
define('\\\\TEST4', 'empty');
define('\\TEST5', 'empty');  // this is valid
echo TEST5;
echo namespace\TEST5;  // should warn

/*
// Note: Apparently, it does succeed if you actually do that (tested in PHP 7.0 and 7.3).
// Still going to warn about it.
php > define('foo\\\\\\TEST3', 'some value');
php > var_export(constant('foo\\\\\\TEST3'));
'some value'
*/
