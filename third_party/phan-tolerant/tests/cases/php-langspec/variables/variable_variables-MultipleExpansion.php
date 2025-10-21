/* Auto-generated from php/php-langspec tests */
<?php

$x = 'ab';
$ab = 'fg';
$fg = 'xy';

$$$$x = 'Hello';		// looks like a unary operator, and associates R->L
//$$$($x) = 'Hello';	// However, CAN'T use grouping parens to document that!!!
echo "\$xy = $xy\n";	// ==> $xy = Hello
$						// can have arbitrary white space separators
 $
 $ $x = 'Hello';
echo "\$xy = $xy\n";

${${${$x}}} = 'Hello';
echo "\$xy = $xy\n";

var_dump($x);
var_dump($ $x);
var_dump($ $ $x);
var_dump($ $ $ $x);
//*/

///*
