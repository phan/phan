--TEST--
-= operator
--FILE--
<?php

require __DIR__ . '/common.inc';

foreach ($oper as $t)
{
	foreach ($oper as $e2)
	{
		$e1 = $t;
		echo ">$e1< -= >$e2<, result: "; var_dump($e1 -= $e2);
	}
	echo "-------------------------------------\n";
}

?>
--EXPECTF--
>0< -= >0<, result: int(0)
>0< -= >-10<, result: int(10)
>0< -= >100<, result: int(-100)
>0< -= >-34000000000<, result: float(34000000000)
>0< -= >INF<, result: float(-INF)
>0< -= >-INF<, result: float(INF)
>0< -= >NAN<, result: float(NAN)
>0< -= >1<, result: int(-1)
>0< -= ><, result: int(0)
>0< -= ><, result: int(0)
>0< -= >123<, result: int(-123)
>0< -= >2e+5<, result: float(-200000)
>0< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< -= >9223372036854775807<, result: int(-9223372036854775807)
-------------------------------------
>-10< -= >0<, result: int(-10)
>-10< -= >-10<, result: int(0)
>-10< -= >100<, result: int(-110)
>-10< -= >-34000000000<, result: float(33999999990)
>-10< -= >INF<, result: float(-INF)
>-10< -= >-INF<, result: float(INF)
>-10< -= >NAN<, result: float(NAN)
>-10< -= >1<, result: int(-11)
>-10< -= ><, result: int(-10)
>-10< -= ><, result: int(-10)
>-10< -= >123<, result: int(-133)
>-10< -= >2e+5<, result: float(-200010)
>-10< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-10)
>-10< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-10)
>-10< -= >9223372036854775807<, result: float(-9.2233720368548E+18)
-------------------------------------
>100< -= >0<, result: int(100)
>100< -= >-10<, result: int(110)
>100< -= >100<, result: int(0)
>100< -= >-34000000000<, result: float(34000000100)
>100< -= >INF<, result: float(-INF)
>100< -= >-INF<, result: float(INF)
>100< -= >NAN<, result: float(NAN)
>100< -= >1<, result: int(99)
>100< -= ><, result: int(100)
>100< -= ><, result: int(100)
>100< -= >123<, result: int(-23)
>100< -= >2e+5<, result: float(-199900)
>100< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(100)
>100< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(100)
>100< -= >9223372036854775807<, result: int(-9223372036854775707)
-------------------------------------
>-34000000000< -= >0<, result: float(-34000000000)
>-34000000000< -= >-10<, result: float(-33999999990)
>-34000000000< -= >100<, result: float(-34000000100)
>-34000000000< -= >-34000000000<, result: float(0)
>-34000000000< -= >INF<, result: float(-INF)
>-34000000000< -= >-INF<, result: float(INF)
>-34000000000< -= >NAN<, result: float(NAN)
>-34000000000< -= >1<, result: float(-34000000001)
>-34000000000< -= ><, result: float(-34000000000)
>-34000000000< -= ><, result: float(-34000000000)
>-34000000000< -= >123<, result: float(-34000000123)
>-34000000000< -= >2e+5<, result: float(-34000200000)
>-34000000000< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-34000000000)
>-34000000000< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-34000000000)
>-34000000000< -= >9223372036854775807<, result: float(-9.2233720708548E+18)
-------------------------------------
>INF< -= >0<, result: float(INF)
>INF< -= >-10<, result: float(INF)
>INF< -= >100<, result: float(INF)
>INF< -= >-34000000000<, result: float(INF)
>INF< -= >INF<, result: float(NAN)
>INF< -= >-INF<, result: float(INF)
>INF< -= >NAN<, result: float(NAN)
>INF< -= >1<, result: float(INF)
>INF< -= ><, result: float(INF)
>INF< -= ><, result: float(INF)
>INF< -= >123<, result: float(INF)
>INF< -= >2e+5<, result: float(INF)
>INF< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(INF)
>INF< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(INF)
>INF< -= >9223372036854775807<, result: float(INF)
-------------------------------------
>-INF< -= >0<, result: float(-INF)
>-INF< -= >-10<, result: float(-INF)
>-INF< -= >100<, result: float(-INF)
>-INF< -= >-34000000000<, result: float(-INF)
>-INF< -= >INF<, result: float(-INF)
>-INF< -= >-INF<, result: float(NAN)
>-INF< -= >NAN<, result: float(NAN)
>-INF< -= >1<, result: float(-INF)
>-INF< -= ><, result: float(-INF)
>-INF< -= ><, result: float(-INF)
>-INF< -= >123<, result: float(-INF)
>-INF< -= >2e+5<, result: float(-INF)
>-INF< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-INF)
>-INF< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-INF)
>-INF< -= >9223372036854775807<, result: float(-INF)
-------------------------------------
>NAN< -= >0<, result: float(NAN)
>NAN< -= >-10<, result: float(NAN)
>NAN< -= >100<, result: float(NAN)
>NAN< -= >-34000000000<, result: float(NAN)
>NAN< -= >INF<, result: float(NAN)
>NAN< -= >-INF<, result: float(NAN)
>NAN< -= >NAN<, result: float(NAN)
>NAN< -= >1<, result: float(NAN)
>NAN< -= ><, result: float(NAN)
>NAN< -= ><, result: float(NAN)
>NAN< -= >123<, result: float(NAN)
>NAN< -= >2e+5<, result: float(NAN)
>NAN< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>NAN< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>NAN< -= >9223372036854775807<, result: float(NAN)
-------------------------------------
>1< -= >0<, result: int(1)
>1< -= >-10<, result: int(11)
>1< -= >100<, result: int(-99)
>1< -= >-34000000000<, result: float(34000000001)
>1< -= >INF<, result: float(-INF)
>1< -= >-INF<, result: float(INF)
>1< -= >NAN<, result: float(NAN)
>1< -= >1<, result: int(0)
>1< -= ><, result: int(1)
>1< -= ><, result: int(1)
>1< -= >123<, result: int(-122)
>1< -= >2e+5<, result: float(-199999)
>1< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(1)
>1< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(1)
>1< -= >9223372036854775807<, result: int(-9223372036854775806)
-------------------------------------
>< -= >0<, result: int(0)
>< -= >-10<, result: int(10)
>< -= >100<, result: int(-100)
>< -= >-34000000000<, result: float(34000000000)
>< -= >INF<, result: float(-INF)
>< -= >-INF<, result: float(INF)
>< -= >NAN<, result: float(NAN)
>< -= >1<, result: int(-1)
>< -= ><, result: int(0)
>< -= ><, result: int(0)
>< -= >123<, result: int(-123)
>< -= >2e+5<, result: float(-200000)
>< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >9223372036854775807<, result: int(-9223372036854775807)
-------------------------------------
>< -= >0<, result: int(0)
>< -= >-10<, result: int(10)
>< -= >100<, result: int(-100)
>< -= >-34000000000<, result: float(34000000000)
>< -= >INF<, result: float(-INF)
>< -= >-INF<, result: float(INF)
>< -= >NAN<, result: float(NAN)
>< -= >1<, result: int(-1)
>< -= ><, result: int(0)
>< -= ><, result: int(0)
>< -= >123<, result: int(-123)
>< -= >2e+5<, result: float(-200000)
>< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >9223372036854775807<, result: int(-9223372036854775807)
-------------------------------------
>123< -= >0<, result: int(123)
>123< -= >-10<, result: int(133)
>123< -= >100<, result: int(23)
>123< -= >-34000000000<, result: float(34000000123)
>123< -= >INF<, result: float(-INF)
>123< -= >-INF<, result: float(INF)
>123< -= >NAN<, result: float(NAN)
>123< -= >1<, result: int(122)
>123< -= ><, result: int(123)
>123< -= ><, result: int(123)
>123< -= >123<, result: int(0)
>123< -= >2e+5<, result: float(-199877)
>123< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(123)
>123< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(123)
>123< -= >9223372036854775807<, result: int(-9223372036854775684)
-------------------------------------
>2e+5< -= >0<, result: float(200000)
>2e+5< -= >-10<, result: float(200010)
>2e+5< -= >100<, result: float(199900)
>2e+5< -= >-34000000000<, result: float(34000200000)
>2e+5< -= >INF<, result: float(-INF)
>2e+5< -= >-INF<, result: float(INF)
>2e+5< -= >NAN<, result: float(NAN)
>2e+5< -= >1<, result: float(199999)
>2e+5< -= ><, result: float(200000)
>2e+5< -= ><, result: float(200000)
>2e+5< -= >123<, result: float(199877)
>2e+5< -= >2e+5<, result: float(0)
>2e+5< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
float(200000)
>2e+5< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(200000)
>2e+5< -= >9223372036854775807<, result: float(-9.2233720368546E+18)
-------------------------------------
>< -= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(10)
>< -= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-100)
>< -= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(34000000000)
>< -= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-INF)
>< -= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(INF)
>< -= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>< -= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-1)
>< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-123)
>< -= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-200000)
>< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>< -= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-9223372036854775807)
-------------------------------------
>abc< -= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< -= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(10)
>abc< -= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-100)
>abc< -= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(34000000000)
>abc< -= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-INF)
>abc< -= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(INF)
>abc< -= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>abc< -= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-1)
>abc< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< -= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-123)
>abc< -= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-200000)
>abc< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< -= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(-9223372036854775807)
-------------------------------------
>9223372036854775807< -= >0<, result: int(9223372036854775807)
>9223372036854775807< -= >-10<, result: float(9.2233720368548E+18)
>9223372036854775807< -= >100<, result: int(9223372036854775707)
>9223372036854775807< -= >-34000000000<, result: float(9.2233720708548E+18)
>9223372036854775807< -= >INF<, result: float(-INF)
>9223372036854775807< -= >-INF<, result: float(INF)
>9223372036854775807< -= >NAN<, result: float(NAN)
>9223372036854775807< -= >1<, result: int(9223372036854775806)
>9223372036854775807< -= ><, result: int(9223372036854775807)
>9223372036854775807< -= ><, result: int(9223372036854775807)
>9223372036854775807< -= >123<, result: int(9223372036854775684)
>9223372036854775807< -= >2e+5<, result: float(9.2233720368546E+18)
>9223372036854775807< -= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(9223372036854775807)
>9223372036854775807< -= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(9223372036854775807)
>9223372036854775807< -= >9223372036854775807<, result: int(0)
-------------------------------------
