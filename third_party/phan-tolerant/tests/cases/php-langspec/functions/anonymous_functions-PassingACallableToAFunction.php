/* Auto-generated from php/php-langspec tests */
<?php

function double($p)
{
	return $p * 2;
}

function square($p)
{
	return $p * $p;
}

function doit($value, callable $process)
{
	var_dump($process);

	return $process($value);
}

$res = doit(10, 'double');
echo "Result of calling doit using function double = $res\n-------\n";

$res = doit(10, 'square');
echo "Result of calling doit using function square = $res\n-------\n";


$res = doit(5, function ($p) { return $p * 2; });
echo "Result of calling doit using double closure = $res\n-------\n";

$res = doit(5, function ($p) { return $p * $p; });
echo "Result of calling doit using square closure = $res\n-------\n";

