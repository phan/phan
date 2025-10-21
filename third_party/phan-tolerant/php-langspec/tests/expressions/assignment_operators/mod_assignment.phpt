--TEST--
%= operator
--FILE--
<?php

require __DIR__ . '/common.inc';

foreach ($oper as $t)
{
	foreach ($oper as $e2)
	{
		if (((int)$e2) == 0) continue;	// skip divide-by-zeros

		$e1 = $t;
		echo ">$e1< %= >$e2<, result: "; var_dump($e1 %= $e2);
	}
	echo "-------------------------------------\n";
}

?>
--EXPECTF--
>0< %= >-10<, result: int(0)
>0< %= >100<, result: int(0)
>0< %= >-34000000000<, result: int(0)
>0< %= >1<, result: int(0)
>0< %= >123<, result: int(0)
>0< %= >2e+5<, result: int(0)
>0< %= >9223372036854775807<, result: int(0)
-------------------------------------
>-10< %= >-10<, result: int(0)
>-10< %= >100<, result: int(-10)
>-10< %= >-34000000000<, result: int(-10)
>-10< %= >1<, result: int(0)
>-10< %= >123<, result: int(-10)
>-10< %= >2e+5<, result: int(-10)
>-10< %= >9223372036854775807<, result: int(-10)
-------------------------------------
>100< %= >-10<, result: int(0)
>100< %= >100<, result: int(0)
>100< %= >-34000000000<, result: int(100)
>100< %= >1<, result: int(0)
>100< %= >123<, result: int(100)
>100< %= >2e+5<, result: int(100)
>100< %= >9223372036854775807<, result: int(100)
-------------------------------------
>-34000000000< %= >-10<, result: int(0)
>-34000000000< %= >100<, result: int(0)
>-34000000000< %= >-34000000000<, result: int(0)
>-34000000000< %= >1<, result: int(0)
>-34000000000< %= >123<, result: int(-28)
>-34000000000< %= >2e+5<, result: int(0)
>-34000000000< %= >9223372036854775807<, result: int(-34000000000)
-------------------------------------
>INF< %= >-10<, result: int(0)
>INF< %= >100<, result: int(0)
>INF< %= >-34000000000<, result: int(0)
>INF< %= >1<, result: int(0)
>INF< %= >123<, result: int(0)
>INF< %= >2e+5<, result: int(0)
>INF< %= >9223372036854775807<, result: int(0)
-------------------------------------
>-INF< %= >-10<, result: int(0)
>-INF< %= >100<, result: int(0)
>-INF< %= >-34000000000<, result: int(0)
>-INF< %= >1<, result: int(0)
>-INF< %= >123<, result: int(0)
>-INF< %= >2e+5<, result: int(0)
>-INF< %= >9223372036854775807<, result: int(0)
-------------------------------------
>NAN< %= >-10<, result: int(0)
>NAN< %= >100<, result: int(0)
>NAN< %= >-34000000000<, result: int(0)
>NAN< %= >1<, result: int(0)
>NAN< %= >123<, result: int(0)
>NAN< %= >2e+5<, result: int(0)
>NAN< %= >9223372036854775807<, result: int(0)
-------------------------------------
>1< %= >-10<, result: int(1)
>1< %= >100<, result: int(1)
>1< %= >-34000000000<, result: int(1)
>1< %= >1<, result: int(0)
>1< %= >123<, result: int(1)
>1< %= >2e+5<, result: int(1)
>1< %= >9223372036854775807<, result: int(1)
-------------------------------------
>< %= >-10<, result: int(0)
>< %= >100<, result: int(0)
>< %= >-34000000000<, result: int(0)
>< %= >1<, result: int(0)
>< %= >123<, result: int(0)
>< %= >2e+5<, result: int(0)
>< %= >9223372036854775807<, result: int(0)
-------------------------------------
>< %= >-10<, result: int(0)
>< %= >100<, result: int(0)
>< %= >-34000000000<, result: int(0)
>< %= >1<, result: int(0)
>< %= >123<, result: int(0)
>< %= >2e+5<, result: int(0)
>< %= >9223372036854775807<, result: int(0)
-------------------------------------
>123< %= >-10<, result: int(3)
>123< %= >100<, result: int(23)
>123< %= >-34000000000<, result: int(123)
>123< %= >1<, result: int(0)
>123< %= >123<, result: int(0)
>123< %= >2e+5<, result: int(123)
>123< %= >9223372036854775807<, result: int(123)
-------------------------------------
>2e+5< %= >-10<, result: int(0)
>2e+5< %= >100<, result: int(0)
>2e+5< %= >-34000000000<, result: int(200000)
>2e+5< %= >1<, result: int(0)
>2e+5< %= >123<, result: int(2)
>2e+5< %= >2e+5<, result: int(0)
>2e+5< %= >9223372036854775807<, result: int(200000)
-------------------------------------
>< %= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< %= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< %= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< %= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< %= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< %= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< %= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>abc< %= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< %= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< %= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< %= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< %= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< %= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< %= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>9223372036854775807< %= >-10<, result: int(7)
>9223372036854775807< %= >100<, result: int(7)
>9223372036854775807< %= >-34000000000<, result: int(4854775807)
>9223372036854775807< %= >1<, result: int(0)
>9223372036854775807< %= >123<, result: int(7)
>9223372036854775807< %= >2e+5<, result: int(175807)
>9223372036854775807< %= >9223372036854775807<, result: int(0)
-------------------------------------
