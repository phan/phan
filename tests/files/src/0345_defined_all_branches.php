<?php

function test($x, $y) : int {
	if ($x) {
	   $a = 2;
	} elseif ($y) {
	   $a = 3;
	} else {
	   return -2;
	}

	echo strlen($a); // should warn about wrong type.
	return $a;
}
