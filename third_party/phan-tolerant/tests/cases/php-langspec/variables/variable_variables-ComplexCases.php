/* Auto-generated from php/php-langspec tests */
<?php

$c1 = new C1;
$c1->pr2 = 'v';

var_dump($c1->pr2);
var_dump($c1);

$$c1->pr2 = 6;			// $w => stdClass { ["pr2"]=>int(6) }
//var_dump($GLOBALS);
${$c1}->pr2 = 7;		// $w => stdClass { ["pr2"]=>int(7) }
//var_dump($GLOBALS);

// The 2 cases above are equivalent. Here's what's happening:
// $c1 is converted to a string via __toString, which gives 'w'.
// The designated variable becomes $w, which does not exist, so it looks like
// it has a value of NULL. Then, when the -> is applied, we get a instance of stdClass.
// The problem then is that the $ operator takes precedence over the ->, which wasn't
// what I expected.

${$c1->pr2} = 8;		// $v = 8
//var_dump($GLOBALS);

unset($v, $w);
//*/

///*
echo "----------------------\n";

function ff() { return "xxx"; }

$res = ff();
$$res = 777;
echo "\$xxx = $xxx\n";
//*/
