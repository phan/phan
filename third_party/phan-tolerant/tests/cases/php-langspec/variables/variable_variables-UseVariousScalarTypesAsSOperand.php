/* Auto-generated from php/php-langspec tests */
<?php

// $'s operand can be a value of any scalar type, but NOT a literal

// string operand

$v1 = 'abc';
$$v1 = '$v1 = \'abc\'';
echo "\$abc = $abc\n";
var_dump($$v1);

// int operand

$v2 = 3;
$$v2 = '$v2 = 3';
var_dump($$v2);
${$v2} = '$v2 = 3';
var_dump(${$v2});
//$3 = '$v2 = 3';
$ {  3  } = '$v2 = 3';
var_dump(${3});

// float operand

$v3 = 9.543;
$$v3 = '$v3 = 9.543';
var_dump($$v3);

// bool operand

$v4 = TRUE;
$$v4 = '$v4 = TRUE';
var_dump($$v4);
$v5 = FALSE;
$$v5 = '$v5 = FALSE';
var_dump($$v5);

// null operand

$v6 = NULL;
$$v6 = '$v6 = NULL';
var_dump($$v6);

//var_dump($GLOBALS);

function f()
{
	// the following work, but the name $'abc' is created in the local scope;
	// it certainly isn't in the Globals array. However, given the global declaration,
	// the name $'3' does designated the global by that name.

	$v11 = 'abc';
	$$v11 = '$v11 = \'abc\'';
	echo "\$abc = $abc\n";
	var_dump($$v11);

	global ${3};

	$v12 = 3;
	$$v12 = '$v12 = 3';		// changes the global
	var_dump($$v12);
}

f();

//var_dump($GLOBALS);
//*/

///*
