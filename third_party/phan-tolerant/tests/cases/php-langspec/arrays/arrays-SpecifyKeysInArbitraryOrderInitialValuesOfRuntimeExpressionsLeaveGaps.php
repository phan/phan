/* Auto-generated from php/php-langspec tests */
<?php

$i = 6;
$j = 12;
$v = array(7 => 123, 3 => $i, 6 => ++$j);
var_dump($v);

$i = 6;
$j = 12;
$v = [7 => 123, 3 => $i, 6 => ++$j];
var_dump($v);

foreach($v as $e)	// only has 3 elements ([3], [6], and [7]), not 8 ([0]-[7])
{
	echo $e.' ';
}
echo "\n";

echo "\$v[1] is >".$v[1]."<\n"; var_dump($v1[1]); // access non-existant element
echo "\$v[4] is >".$v[4]."<\n"; var_dump($v1[4]); // access non-existant element

$v[1] = TRUE;		// increases array to 4 elements
$v[4] = 99;			// increases array to 5 elements
var_dump($v);
foreach($v as $e)		// now has 5 elements
{
	echo $e.' ';
}
echo "\n";

