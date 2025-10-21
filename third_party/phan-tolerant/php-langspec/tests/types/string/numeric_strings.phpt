--TEST--
PHP Spec test generated from ./types/string/numeric_strings.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

var_dump(setlocale(LC_ALL, 0));
var_dump(setlocale(LC_NUMERIC, "fr-CA"));
var_dump(setlocale(LC_NUMERIC, "fr-BE"));
var_dump(setlocale(LC_NUMERIC, "fr-CH"));
var_dump(setlocale(LC_NUMERIC, "fr-FR"));
// var_dump(setlocale(LC_NUMERIC, "XXX"));	// returns False as there is no such locale on system

$s = array(
	"",
	"0", "00", "0377", "0xEEFFAA00", "0X1234EF",
	" 0", "  00", "   0377", "    0xEEFFAA00", "     0X1234EF",
	"0 ", "00  ", "0377   ", "0xEEFFAA00    ", "0X1234EF     ",
	"0b1010", "0B111111111111111",
	"+0", "+1234567890", "-187654321",
	"123.456", "-7654.", ".7654321",
	"1e12", "+2E21", "-4e+2", "9E-21",
	"-123.762e21", "+876.432E37",
	"INF", "INf", "InF", "Inf", "iNF", "iNf", "inF", "inf",
	"+INF", "+INf", "+InF", "+Inf", "+iNF", "+iNf", "+inF", "+inf",
	"-INF", "-INf", "-InF", "-Inf", "-iNF", "-iNf", "-inF", "-inf",
	"NAN", "NAn", "NaN", "Nan", "nAN", "nAn", "naN", "nan"
);

foreach ($s as $e) {
	echo ">$e< is ".(is_numeric($e) ? "numeric\n" : "not numeric\n");
}

sprintf($t, ",%b,%B", 0b1010, 0b1010);
var_dump($t);
var_dump((string)INF);
var_dump((string)-INF);
var_dump((string)NAN);
--EXPECTF--
string(%d) "%s"
bool(false)
bool(false)
bool(false)
bool(false)
>< is not numeric
>0< is numeric
>00< is numeric
>0377< is numeric
>0xEEFFAA00< is not numeric
>0X1234EF< is not numeric
> 0< is numeric
>  00< is numeric
>   0377< is numeric
>    0xEEFFAA00< is not numeric
>     0X1234EF< is not numeric
>0 < is not numeric
>00  < is not numeric
>0377   < is not numeric
>0xEEFFAA00    < is not numeric
>0X1234EF     < is not numeric
>0b1010< is not numeric
>0B111111111111111< is not numeric
>+0< is numeric
>+1234567890< is numeric
>-187654321< is numeric
>123.456< is numeric
>-7654.< is numeric
>.7654321< is numeric
>1e12< is numeric
>+2E21< is numeric
>-4e+2< is numeric
>9E-21< is numeric
>-123.762e21< is numeric
>+876.432E37< is numeric
>INF< is not numeric
>INf< is not numeric
>InF< is not numeric
>Inf< is not numeric
>iNF< is not numeric
>iNf< is not numeric
>inF< is not numeric
>inf< is not numeric
>+INF< is not numeric
>+INf< is not numeric
>+InF< is not numeric
>+Inf< is not numeric
>+iNF< is not numeric
>+iNf< is not numeric
>+inF< is not numeric
>+inf< is not numeric
>-INF< is not numeric
>-INf< is not numeric
>-InF< is not numeric
>-Inf< is not numeric
>-iNF< is not numeric
>-iNf< is not numeric
>-inF< is not numeric
>-inf< is not numeric
>NAN< is not numeric
>NAn< is not numeric
>NaN< is not numeric
>Nan< is not numeric
>nAN< is not numeric
>nAn< is not numeric
>naN< is not numeric
>nan< is not numeric

Notice: Undefined variable: t in %s/types/string/numeric_strings.php on line 38

Notice: Undefined variable: t in %s/types/string/numeric_strings.php on line 39
NULL
string(3) "INF"
string(4) "-INF"
string(3) "NAN"
