<?php 

function try_ref(&$var) {
	try {
		$var = 1;
	} catch (PDOException $_) {
		$var = 2;
	} catch (Exception $_) {
		$var = 3;
	}
}

try_ref($var);
'@phan-debug-var $var';

function try_ref_ret(&$var) {
	try {
		$var = 4;
		return;
	} catch (PDOException $_) {
		$var = 5;
		return;
	} catch (Exception $_) {
		$var = 6;
		return;
	}
}

try_ref_ret($var2);
'@phan-debug-var $var2';

function try_ref_mixed(&$var) {
	try {
		$var = 7;
	} catch (PDOException $_) {
		$var = 8;
		return;
	} catch (Exception $_) {
		$var = 9;
		return;
	}
}

try_ref_mixed($var3);
'@phan-debug-var $var3';

function if_ref(&$var) {
	if (rand(0,1)) {
		$var = 10;
		return;
	} elseif (rand(0,2)) {
		$var = 11;
		return;
	} else {
		$var = 12;
		return;
	}
}

if_ref($var4);
'@phan-debug-var $var4';

function early_ret(&$var) {
	if (rand(0,1)) {
		return;
	}
	$var = 14;
}

$var5 = 13;
early_ret($var5);
'@phan-debug-var $var5';

function inner() {
	if (rand(0,1)) {
		throw new Exception();
	}
}

function middle(&$var) {
	inner();
	$var = 16;
}

function outer() {
	$var = 15;
	try {
		middle($var);
		return $var;
	} catch (Exception $_) {
		return $var;
	}
}

$var6 = outer();
'@phan-debug-var $var6';