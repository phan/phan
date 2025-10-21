--TEST--
*= operator
--FILE--
<?php

require __DIR__ . '/common.inc';

foreach ($oper as $t)
{
	foreach ($oper as $e2)
	{
		$e1 = $t;
		echo ">$e1< *= >$e2<, result: "; var_dump($e1 *= $e2);
	}
	echo "-------------------------------------\n";
}

?>
--EXPECTF--
>0< *= >0<, result: int(0)
>0< *= >-10<, result: int(0)
>0< *= >100<, result: int(0)
>0< *= >-34000000000<, result: float(-0)
>0< *= >INF<, result: float(NAN)
>0< *= >-INF<, result: float(NAN)
>0< *= >NAN<, result: float(NAN)
>0< *= >1<, result: int(0)
>0< *= ><, result: int(0)
>0< *= ><, result: int(0)
>0< *= >123<, result: int(0)
>0< *= >2e+5<, result: float(0)
>0< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< *= >9223372036854775807<, result: int(0)
-------------------------------------
>-10< *= >0<, result: int(0)
>-10< *= >-10<, result: int(100)
>-10< *= >100<, result: int(-1000)
>-10< *= >-34000000000<, result: float(340000000000)
>-10< *= >INF<, result: float(-INF)
>-10< *= >-INF<, result: float(INF)
>-10< *= >NAN<, result: float(NAN)
>-10< *= >1<, result: int(-10)
>-10< *= ><, result: int(0)
>-10< *= ><, result: int(0)
>-10< *= >123<, result: int(-1230)
>-10< *= >2e+5<, result: float(-2000000)
>-10< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-10< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-10< *= >9223372036854775807<, result: float(-9.2233720368548E+19)
-------------------------------------
>100< *= >0<, result: int(0)
>100< *= >-10<, result: int(-1000)
>100< *= >100<, result: int(10000)
>100< *= >-34000000000<, result: float(-3400000000000)
>100< *= >INF<, result: float(INF)
>100< *= >-INF<, result: float(-INF)
>100< *= >NAN<, result: float(NAN)
>100< *= >1<, result: int(100)
>100< *= ><, result: int(0)
>100< *= ><, result: int(0)
>100< *= >123<, result: int(12300)
>100< *= >2e+5<, result: float(20000000)
>100< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>100< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>100< *= >9223372036854775807<, result: float(9.2233720368548E+20)
-------------------------------------
>-34000000000< *= >0<, result: float(-0)
>-34000000000< *= >-10<, result: float(340000000000)
>-34000000000< *= >100<, result: float(-3400000000000)
>-34000000000< *= >-34000000000<, result: float(1.156E+21)
>-34000000000< *= >INF<, result: float(-INF)
>-34000000000< *= >-INF<, result: float(INF)
>-34000000000< *= >NAN<, result: float(NAN)
>-34000000000< *= >1<, result: float(-34000000000)
>-34000000000< *= ><, result: float(-0)
>-34000000000< *= ><, result: float(-0)
>-34000000000< *= >123<, result: float(-4182000000000)
>-34000000000< *= >2e+5<, result: float(-6.8E+15)
>-34000000000< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>-34000000000< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>-34000000000< *= >9223372036854775807<, result: float(-3.1359464925306E+29)
-------------------------------------
>INF< *= >0<, result: float(NAN)
>INF< *= >-10<, result: float(-INF)
>INF< *= >100<, result: float(INF)
>INF< *= >-34000000000<, result: float(-INF)
>INF< *= >INF<, result: float(INF)
>INF< *= >-INF<, result: float(-INF)
>INF< *= >NAN<, result: float(NAN)
>INF< *= >1<, result: float(INF)
>INF< *= ><, result: float(NAN)
>INF< *= ><, result: float(NAN)
>INF< *= >123<, result: float(INF)
>INF< *= >2e+5<, result: float(INF)
>INF< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>INF< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>INF< *= >9223372036854775807<, result: float(INF)
-------------------------------------
>-INF< *= >0<, result: float(NAN)
>-INF< *= >-10<, result: float(INF)
>-INF< *= >100<, result: float(-INF)
>-INF< *= >-34000000000<, result: float(INF)
>-INF< *= >INF<, result: float(-INF)
>-INF< *= >-INF<, result: float(INF)
>-INF< *= >NAN<, result: float(NAN)
>-INF< *= >1<, result: float(-INF)
>-INF< *= ><, result: float(NAN)
>-INF< *= ><, result: float(NAN)
>-INF< *= >123<, result: float(-INF)
>-INF< *= >2e+5<, result: float(-INF)
>-INF< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>-INF< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>-INF< *= >9223372036854775807<, result: float(-INF)
-------------------------------------
>NAN< *= >0<, result: float(NAN)
>NAN< *= >-10<, result: float(NAN)
>NAN< *= >100<, result: float(NAN)
>NAN< *= >-34000000000<, result: float(NAN)
>NAN< *= >INF<, result: float(NAN)
>NAN< *= >-INF<, result: float(NAN)
>NAN< *= >NAN<, result: float(NAN)
>NAN< *= >1<, result: float(NAN)
>NAN< *= ><, result: float(NAN)
>NAN< *= ><, result: float(NAN)
>NAN< *= >123<, result: float(NAN)
>NAN< *= >2e+5<, result: float(NAN)
>NAN< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>NAN< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>NAN< *= >9223372036854775807<, result: float(NAN)
-------------------------------------
>1< *= >0<, result: int(0)
>1< *= >-10<, result: int(-10)
>1< *= >100<, result: int(100)
>1< *= >-34000000000<, result: float(-34000000000)
>1< *= >INF<, result: float(INF)
>1< *= >-INF<, result: float(-INF)
>1< *= >NAN<, result: float(NAN)
>1< *= >1<, result: int(1)
>1< *= ><, result: int(0)
>1< *= ><, result: int(0)
>1< *= >123<, result: int(123)
>1< *= >2e+5<, result: float(200000)
>1< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>1< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>1< *= >9223372036854775807<, result: int(9223372036854775807)
-------------------------------------
>< *= >0<, result: int(0)
>< *= >-10<, result: int(0)
>< *= >100<, result: int(0)
>< *= >-34000000000<, result: float(-0)
>< *= >INF<, result: float(NAN)
>< *= >-INF<, result: float(NAN)
>< *= >NAN<, result: float(NAN)
>< *= >1<, result: int(0)
>< *= ><, result: int(0)
>< *= ><, result: int(0)
>< *= >123<, result: int(0)
>< *= >2e+5<, result: float(0)
>< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >9223372036854775807<, result: int(0)
-------------------------------------
>< *= >0<, result: int(0)
>< *= >-10<, result: int(0)
>< *= >100<, result: int(0)
>< *= >-34000000000<, result: float(-0)
>< *= >INF<, result: float(NAN)
>< *= >-INF<, result: float(NAN)
>< *= >NAN<, result: float(NAN)
>< *= >1<, result: int(0)
>< *= ><, result: int(0)
>< *= ><, result: int(0)
>< *= >123<, result: int(0)
>< *= >2e+5<, result: float(0)
>< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >9223372036854775807<, result: int(0)
-------------------------------------
>123< *= >0<, result: int(0)
>123< *= >-10<, result: int(-1230)
>123< *= >100<, result: int(12300)
>123< *= >-34000000000<, result: float(-4182000000000)
>123< *= >INF<, result: float(INF)
>123< *= >-INF<, result: float(-INF)
>123< *= >NAN<, result: float(NAN)
>123< *= >1<, result: int(123)
>123< *= ><, result: int(0)
>123< *= ><, result: int(0)
>123< *= >123<, result: int(15129)
>123< *= >2e+5<, result: float(24600000)
>123< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>123< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>123< *= >9223372036854775807<, result: float(1.1344747605331E+21)
-------------------------------------
>2e+5< *= >0<, result: float(0)
>2e+5< *= >-10<, result: float(-2000000)
>2e+5< *= >100<, result: float(20000000)
>2e+5< *= >-34000000000<, result: float(-6.8E+15)
>2e+5< *= >INF<, result: float(INF)
>2e+5< *= >-INF<, result: float(-INF)
>2e+5< *= >NAN<, result: float(NAN)
>2e+5< *= >1<, result: float(200000)
>2e+5< *= ><, result: float(0)
>2e+5< *= ><, result: float(0)
>2e+5< *= >123<, result: float(24600000)
>2e+5< *= >2e+5<, result: float(40000000000)
>2e+5< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>2e+5< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>2e+5< *= >9223372036854775807<, result: float(1.844674407371E+24)
-------------------------------------
>< *= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>< *= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>< *= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>< *= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>< *= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>< *= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>abc< *= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>abc< *= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>abc< *= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>abc< *= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>abc< *= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>abc< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< *= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>9223372036854775807< *= >0<, result: int(0)
>9223372036854775807< *= >-10<, result: float(-9.2233720368548E+19)
>9223372036854775807< *= >100<, result: float(9.2233720368548E+20)
>9223372036854775807< *= >-34000000000<, result: float(-3.1359464925306E+29)
>9223372036854775807< *= >INF<, result: float(INF)
>9223372036854775807< *= >-INF<, result: float(-INF)
>9223372036854775807< *= >NAN<, result: float(NAN)
>9223372036854775807< *= >1<, result: int(9223372036854775807)
>9223372036854775807< *= ><, result: int(0)
>9223372036854775807< *= ><, result: int(0)
>9223372036854775807< *= >123<, result: float(1.1344747605331E+21)
>9223372036854775807< *= >2e+5<, result: float(1.844674407371E+24)
>9223372036854775807< *= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>9223372036854775807< *= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>9223372036854775807< *= >9223372036854775807<, result: float(8.5070591730235E+37)
-------------------------------------
