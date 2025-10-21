--TEST--
>>= operator
--FILE--
<?php

require __DIR__ . '/common.inc';

foreach ($oper as $t)
{
	foreach ($oper as $e2)
	{
		if ((int)($e2) < 0) continue;	// skip negative shifts
        
		$e1 = $t;
		echo ">$e1< >>= >$e2<, result: "; var_dump($e1 <<= $e2);
	}
	echo "-------------------------------------\n";
}

?>
--EXPECTF--
>0< >>= >0<, result: int(0)
>0< >>= >100<, result: int(0)
>0< >>= >INF<, result: int(0)
>0< >>= >-INF<, result: int(0)
>0< >>= >NAN<, result: int(0)
>0< >>= >1<, result: int(0)
>0< >>= ><, result: int(0)
>0< >>= ><, result: int(0)
>0< >>= >123<, result: int(0)
>0< >>= >2e+5<, result: int(0)
>0< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>-10< >>= >0<, result: int(-10)
>-10< >>= >100<, result: int(0)
>-10< >>= >INF<, result: int(-10)
>-10< >>= >-INF<, result: int(-10)
>-10< >>= >NAN<, result: int(-10)
>-10< >>= >1<, result: int(-20)
>-10< >>= ><, result: int(-10)
>-10< >>= ><, result: int(-10)
>-10< >>= >123<, result: int(0)
>-10< >>= >2e+5<, result: int(0)
>-10< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-10)
>-10< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-10)
>-10< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>100< >>= >0<, result: int(100)
>100< >>= >100<, result: int(0)
>100< >>= >INF<, result: int(100)
>100< >>= >-INF<, result: int(100)
>100< >>= >NAN<, result: int(100)
>100< >>= >1<, result: int(200)
>100< >>= ><, result: int(100)
>100< >>= ><, result: int(100)
>100< >>= >123<, result: int(0)
>100< >>= >2e+5<, result: int(0)
>100< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(100)
>100< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(100)
>100< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>-34000000000< >>= >0<, result: int(-34000000000)
>-34000000000< >>= >100<, result: int(0)
>-34000000000< >>= >INF<, result: int(-34000000000)
>-34000000000< >>= >-INF<, result: int(-34000000000)
>-34000000000< >>= >NAN<, result: int(-34000000000)
>-34000000000< >>= >1<, result: int(-68000000000)
>-34000000000< >>= ><, result: int(-34000000000)
>-34000000000< >>= ><, result: int(-34000000000)
>-34000000000< >>= >123<, result: int(0)
>-34000000000< >>= >2e+5<, result: int(0)
>-34000000000< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-34000000000)
>-34000000000< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-34000000000)
>-34000000000< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>INF< >>= >0<, result: int(0)
>INF< >>= >100<, result: int(0)
>INF< >>= >INF<, result: int(0)
>INF< >>= >-INF<, result: int(0)
>INF< >>= >NAN<, result: int(0)
>INF< >>= >1<, result: int(0)
>INF< >>= ><, result: int(0)
>INF< >>= ><, result: int(0)
>INF< >>= >123<, result: int(0)
>INF< >>= >2e+5<, result: int(0)
>INF< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>INF< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>INF< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>-INF< >>= >0<, result: int(0)
>-INF< >>= >100<, result: int(0)
>-INF< >>= >INF<, result: int(0)
>-INF< >>= >-INF<, result: int(0)
>-INF< >>= >NAN<, result: int(0)
>-INF< >>= >1<, result: int(0)
>-INF< >>= ><, result: int(0)
>-INF< >>= ><, result: int(0)
>-INF< >>= >123<, result: int(0)
>-INF< >>= >2e+5<, result: int(0)
>-INF< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-INF< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-INF< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>NAN< >>= >0<, result: int(0)
>NAN< >>= >100<, result: int(0)
>NAN< >>= >INF<, result: int(0)
>NAN< >>= >-INF<, result: int(0)
>NAN< >>= >NAN<, result: int(0)
>NAN< >>= >1<, result: int(0)
>NAN< >>= ><, result: int(0)
>NAN< >>= ><, result: int(0)
>NAN< >>= >123<, result: int(0)
>NAN< >>= >2e+5<, result: int(0)
>NAN< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>NAN< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>NAN< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>1< >>= >0<, result: int(1)
>1< >>= >100<, result: int(0)
>1< >>= >INF<, result: int(1)
>1< >>= >-INF<, result: int(1)
>1< >>= >NAN<, result: int(1)
>1< >>= >1<, result: int(2)
>1< >>= ><, result: int(1)
>1< >>= ><, result: int(1)
>1< >>= >123<, result: int(0)
>1< >>= >2e+5<, result: int(0)
>1< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(1)
>1< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(1)
>1< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>< >>= >0<, result: int(0)
>< >>= >100<, result: int(0)
>< >>= >INF<, result: int(0)
>< >>= >-INF<, result: int(0)
>< >>= >NAN<, result: int(0)
>< >>= >1<, result: int(0)
>< >>= ><, result: int(0)
>< >>= ><, result: int(0)
>< >>= >123<, result: int(0)
>< >>= >2e+5<, result: int(0)
>< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>< >>= >0<, result: int(0)
>< >>= >100<, result: int(0)
>< >>= >INF<, result: int(0)
>< >>= >-INF<, result: int(0)
>< >>= >NAN<, result: int(0)
>< >>= >1<, result: int(0)
>< >>= ><, result: int(0)
>< >>= ><, result: int(0)
>< >>= >123<, result: int(0)
>< >>= >2e+5<, result: int(0)
>< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>123< >>= >0<, result: int(123)
>123< >>= >100<, result: int(0)
>123< >>= >INF<, result: int(123)
>123< >>= >-INF<, result: int(123)
>123< >>= >NAN<, result: int(123)
>123< >>= >1<, result: int(246)
>123< >>= ><, result: int(123)
>123< >>= ><, result: int(123)
>123< >>= >123<, result: int(0)
>123< >>= >2e+5<, result: int(0)
>123< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(123)
>123< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(123)
>123< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>2e+5< >>= >0<, result: int(200000)
>2e+5< >>= >100<, result: int(0)
>2e+5< >>= >INF<, result: int(200000)
>2e+5< >>= >-INF<, result: int(200000)
>2e+5< >>= >NAN<, result: int(200000)
>2e+5< >>= >1<, result: int(400000)
>2e+5< >>= ><, result: int(200000)
>2e+5< >>= ><, result: int(200000)
>2e+5< >>= >123<, result: int(0)
>2e+5< >>= >2e+5<, result: int(0)
>2e+5< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(200000)
>2e+5< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(200000)
>2e+5< >>= >9223372036854775807<, result: int(0)
-------------------------------------
>< >>= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>< >>= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>abc< >>= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< >>= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>9223372036854775807< >>= >0<, result: int(9223372036854775807)
>9223372036854775807< >>= >100<, result: int(0)
>9223372036854775807< >>= >INF<, result: int(9223372036854775807)
>9223372036854775807< >>= >-INF<, result: int(9223372036854775807)
>9223372036854775807< >>= >NAN<, result: int(9223372036854775807)
>9223372036854775807< >>= >1<, result: int(-2)
>9223372036854775807< >>= ><, result: int(9223372036854775807)
>9223372036854775807< >>= ><, result: int(9223372036854775807)
>9223372036854775807< >>= >123<, result: int(0)
>9223372036854775807< >>= >2e+5<, result: int(0)
>9223372036854775807< >>= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(9223372036854775807)
>9223372036854775807< >>= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(9223372036854775807)
>9223372036854775807< >>= >9223372036854775807<, result: int(0)
-------------------------------------
