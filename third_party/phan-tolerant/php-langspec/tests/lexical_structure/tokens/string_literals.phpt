--TEST--
PHP Spec test generated from ./lexical_structure/tokens/string_literals.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

echo "================= xxx =================\n";

$x = 123;

///*
// single-quote string literals

var_dump('');
echo '>'.''."<\n";
echo '>'.' a B c '."<\n";
echo '>'.'\'.\".\\.\$.\eXXX.\f.\n.\r.\t.\v.\101.\x41.\X41.\F.\Q.\S'."<\n";
echo '>\$x.$x'."<\n";
echo '>xxx    // this comment-like thingy really is part of the string literal
yyy
zzz'."<\n";

echo var_dump('\e');	// Length should be 2
//*/

///*
// double-quote string literals

var_dump("");
echo '>'.""."<\n";
echo '>'." a B c "."<\n";
echo '>'."\'.\".\\.\$.\eXXX.\f.\n.\r.\t.\v.\101.\x41.\X41.\F.\Q.\S"."<\n";
echo ">\$x.$x"."<\n";
echo ">xxx    // this comment-like thingy really is part of the string literal
yyy
zzz"."<\n";

// the \e test is to prove that HHVM has a bug in that it doesn't recognize this escape sequence

echo var_dump("\e");	// Length should be 1
echo var_dump("\033");	// Length should be 1
echo var_dump("\x1B");	// Length should be 1
echo var_dump("\X1b");	// Length should be 1
//*/

///*
// check all the scalar types for substitution

$a = 435;			var_dump("$a");
$b = -12.34E23;		var_dump("$b");
$c = FALSE;			var_dump("$c");
$d = TRUE;			var_dump("$d");
$e = NULL;			var_dump("$e");
$f = "blue sky";	var_dump("$f");
echo ">$a|$b|$c|$d|$e|$f<\n";

$s = sprintf("%d|%G|%s|%s|%s|%s", $a, $b, $c, $d, $e, $f);
echo ">$s<\n";

$fpvalues = array(24.543567891234565, -2345e25, 6E-200, NAN, INF);
foreach ($fpvalues as $fpval)
{
	echo ">$fpval<--- o/p from string substition\n";
	$s = sprintf("%.14G", $fpval);
	echo ">$s<--- using o/p from sprintf with hard-coded precision\n";
//	$s = sprintf("%.*G", 14, $fpval);
//	echo ">$s<--- using o/p from sprintf with variable precision\n";
}

$fpval = NAN;
echo ">$fpval<--- o/p from string substition\n";
$s = sprintf("%.14F", $fpval);
echo ">$s<--- using o/p from sprintf with hard-coded precision\n";
//*/

///*
// show that the parser must form the longest posisble variable name and that
// for unknown variables a "" is substituted

$z = -34;
$zz = "ABC";
$zzz = TRUE;
$zzzz = 567e12;
echo ">$zX|$z X|$zz_|$zz _|$zzz3|$zzz 3|$zzzz+|$zzzz +<\n";

var_dump("$zX");
var_dump("$zz_");
var_dump("$zzz3");
var_dump("$zzzz+");

// $s not preceding a variable name are used verbatim
echo ">$1|$&<\n";
//*/

///*
// use arrays and array elements

$colors = array("red", "white", "blue");
echo "\colors contains >$colors<\n";
echo "\colors[1] contains >$colors[1]<\n";
echo "\colors[1] contains >$colors [1]<\n";		// whitespace permitted, but semantics change
//echo "\colors[1] contains >$colors[ 1]<\n";	// whitespace not permitted
//echo "\colors[1] contains >$colors[1 ]<\n";	// whitespace not permitted
var_dump("$colors[1]");
var_dump("$colors[01]");		// invalid index
var_dump("$colors[0x1]");		// invalid index
var_dump("$colors[0X1]");		// invalid index

$index = 2;
echo "\$colors[$index] contains >$colors[$index]<\n";
$indices = array(2, 1, 0);
// echo "\colors[$indices[0]] contains >$colors[$indices[0]]<\n"; // the subscript cannot itself be
// other than a simple variable
//*/

///*
$a1 = array(10,20);
$a2 = array(FALSE,10.3,NULL);
echo ">$a1|$a2<\n";

// use class properties

class C {
    public $p1 = 2;
}
$myC = new C();

//echo "\$myC = >$myC<\n";	// can't use an object instance
echo "\$myC->p1 = >$myC->p1<\n";
//echo "\$myC ->p1 = >$myC ->p1<\n";	// whitespace not permitted
//echo "\$myC-> p1 = >$myC-> p1<\n";	// whitespace not permitted

//echo "\colors[$indices[$myC->p1]] contains >$colors[$indices[$myC->p1]]<\n"; // not permitted
//*/

///*
// use brace-delimited expressions

// braces can be use around varible names to stop a longer name being formed

echo ">{$z}X|$z X|{$zz}_|$zz _|{$zzz}3|$zzz 3|{$zzzz}+|$zzzz +<\n";
//*/
// braces having no special meaning are used verbatim

echo ">{}|{q}|}|{<\n";

$a = 10;
echo "{$a  }\n";		// trailing white space is ignored
--EXPECTF--
================= xxx =================
string(0) ""
><
> a B c <
>'.\".\.\$.\eXXX.\f.\n.\r.\t.\v.\101.\x41.\X41.\F.\Q.\S<
>\$x.$x<
>xxx    // this comment-like thingy really is part of the string literal
yyy
zzz<
string(2) "\e"
string(0) ""
><
> a B c <
>\'.".\.$.XXX..
..	..A.A.A.\F.\Q.\S<
>$x.123<
>xxx    // this comment-like thingy really is part of the string literal
yyy
zzz<
string(1) ""
string(1) ""
string(1) ""
string(1) ""
string(3) "435"
string(10) "-1.234E+24"
string(0) ""
string(1) "1"
string(0) ""
string(8) "blue sky"
>435|-1.234E+24||1||blue sky<
>435|-1.234E+24||1||blue sky<
>24.543567891235<--- o/p from string substition
>24.543567891235<--- using o/p from sprintf with hard-coded precision
>-2.345E+28<--- o/p from string substition
>-2.345E+28<--- using o/p from sprintf with hard-coded precision
>6.0E-200<--- o/p from string substition
>6.0E-200<--- using o/p from sprintf with hard-coded precision
>NAN<--- o/p from string substition
>NaN<--- using o/p from sprintf with hard-coded precision
>INF<--- o/p from string substition
>INF<--- using o/p from sprintf with hard-coded precision
>NAN<--- o/p from string substition
>NaN<--- using o/p from sprintf with hard-coded precision

Notice: Undefined variable: zX in %s/lexical_structure/tokens/string_literals.php on line 88

Notice: Undefined variable: zz_ in %s/lexical_structure/tokens/string_literals.php on line 88

Notice: Undefined variable: zzz3 in %s/lexical_structure/tokens/string_literals.php on line 88
>|-34 X||ABC _||1 3|5.67E+14+|5.67E+14 +<

Notice: Undefined variable: zX in %s/lexical_structure/tokens/string_literals.php on line 90
string(0) ""

Notice: Undefined variable: zz_ in %s/lexical_structure/tokens/string_literals.php on line 91
string(0) ""

Notice: Undefined variable: zzz3 in %s/lexical_structure/tokens/string_literals.php on line 92
string(0) ""
string(9) "5.67E+14+"
>$1|$&<

Notice: Array to string conversion in %s/lexical_structure/tokens/string_literals.php on line 103
\colors contains >Array<
\colors[1] contains >white<

Notice: Array to string conversion in %s/lexical_structure/tokens/string_literals.php on line 105
\colors[1] contains >Array [1]<
string(5) "white"

Notice: Undefined index: 01 in %s/lexical_structure/tokens/string_literals.php on line 109
string(0) ""

Notice: Undefined index: 0x1 in %s/lexical_structure/tokens/string_literals.php on line 110
string(0) ""

Notice: Undefined index: 0X1 in %s/lexical_structure/tokens/string_literals.php on line 111
string(0) ""
$colors[2] contains >blue<

Notice: Array to string conversion in %s/lexical_structure/tokens/string_literals.php on line 123

Notice: Array to string conversion in %s/lexical_structure/tokens/string_literals.php on line 123
>Array|Array<
$myC->p1 = >2<
>-34X|-34 X|ABC_|ABC _|13|1 3|5.67E+14+|5.67E+14 +<
>{}|{q}|}|{<
10
