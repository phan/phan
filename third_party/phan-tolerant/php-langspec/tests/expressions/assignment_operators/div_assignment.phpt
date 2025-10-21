--TEST--
/= operator
--FILE--
<?php

require __DIR__ . '/common.inc';

foreach ($oper as $t)
{
	foreach ($oper as $e2)
	{
		if (($e2) == 0) continue;	// skip divide-by-zeros

		$e1 = $t;
		echo ">$e1< /= >$e2<, result: "; var_dump($e1 /= $e2);
	}
	echo "-------------------------------------\n";
}

?>
--EXPECTF--
>0< /= >-10<, result: int(0)
>0< /= >100<, result: int(0)
>0< /= >-34000000000<, result: float(-0)
>0< /= >INF<, result: float(0)
>0< /= >-INF<, result: float(-0)
>0< /= >NAN<, result: float(NAN)
>0< /= >1<, result: int(0)
>0< /= >123<, result: int(0)
>0< /= >2e+5<, result: float(0)
>0< /= >9223372036854775807<, result: int(0)
-------------------------------------
>-10< /= >-10<, result: int(1)
>-10< /= >100<, result: float(-0.1)
>-10< /= >-34000000000<, result: float(2.9411764705882E-10)
>-10< /= >INF<, result: float(-0)
>-10< /= >-INF<, result: float(0)
>-10< /= >NAN<, result: float(NAN)
>-10< /= >1<, result: int(-10)
>-10< /= >123<, result: float(-0.08130081300813)
>-10< /= >2e+5<, result: float(-5.0E-5)
>-10< /= >9223372036854775807<, result: float(-1.0842021724855E-18)
-------------------------------------
>100< /= >-10<, result: int(-10)
>100< /= >100<, result: int(1)
>100< /= >-34000000000<, result: float(-2.9411764705882E-9)
>100< /= >INF<, result: float(0)
>100< /= >-INF<, result: float(-0)
>100< /= >NAN<, result: float(NAN)
>100< /= >1<, result: int(100)
>100< /= >123<, result: float(0.8130081300813)
>100< /= >2e+5<, result: float(0.0005)
>100< /= >9223372036854775807<, result: float(1.0842021724855E-17)
-------------------------------------
>-34000000000< /= >-10<, result: float(3400000000)
>-34000000000< /= >100<, result: float(-340000000)
>-34000000000< /= >-34000000000<, result: float(1)
>-34000000000< /= >INF<, result: float(-0)
>-34000000000< /= >-INF<, result: float(0)
>-34000000000< /= >NAN<, result: float(NAN)
>-34000000000< /= >1<, result: float(-34000000000)
>-34000000000< /= >123<, result: float(-276422764.22764)
>-34000000000< /= >2e+5<, result: float(-170000)
>-34000000000< /= >9223372036854775807<, result: float(-3.6862873864507E-9)
-------------------------------------
>INF< /= >-10<, result: float(-INF)
>INF< /= >100<, result: float(INF)
>INF< /= >-34000000000<, result: float(-INF)
>INF< /= >INF<, result: float(NAN)
>INF< /= >-INF<, result: float(NAN)
>INF< /= >NAN<, result: float(NAN)
>INF< /= >1<, result: float(INF)
>INF< /= >123<, result: float(INF)
>INF< /= >2e+5<, result: float(INF)
>INF< /= >9223372036854775807<, result: float(INF)
-------------------------------------
>-INF< /= >-10<, result: float(INF)
>-INF< /= >100<, result: float(-INF)
>-INF< /= >-34000000000<, result: float(INF)
>-INF< /= >INF<, result: float(NAN)
>-INF< /= >-INF<, result: float(NAN)
>-INF< /= >NAN<, result: float(NAN)
>-INF< /= >1<, result: float(-INF)
>-INF< /= >123<, result: float(-INF)
>-INF< /= >2e+5<, result: float(-INF)
>-INF< /= >9223372036854775807<, result: float(-INF)
-------------------------------------
>NAN< /= >-10<, result: float(NAN)
>NAN< /= >100<, result: float(NAN)
>NAN< /= >-34000000000<, result: float(NAN)
>NAN< /= >INF<, result: float(NAN)
>NAN< /= >-INF<, result: float(NAN)
>NAN< /= >NAN<, result: float(NAN)
>NAN< /= >1<, result: float(NAN)
>NAN< /= >123<, result: float(NAN)
>NAN< /= >2e+5<, result: float(NAN)
>NAN< /= >9223372036854775807<, result: float(NAN)
-------------------------------------
>1< /= >-10<, result: float(-0.1)
>1< /= >100<, result: float(0.01)
>1< /= >-34000000000<, result: float(-2.9411764705882E-11)
>1< /= >INF<, result: float(0)
>1< /= >-INF<, result: float(-0)
>1< /= >NAN<, result: float(NAN)
>1< /= >1<, result: int(1)
>1< /= >123<, result: float(0.008130081300813)
>1< /= >2e+5<, result: float(5.0E-6)
>1< /= >9223372036854775807<, result: float(1.0842021724855E-19)
-------------------------------------
>< /= >-10<, result: int(0)
>< /= >100<, result: int(0)
>< /= >-34000000000<, result: float(-0)
>< /= >INF<, result: float(0)
>< /= >-INF<, result: float(-0)
>< /= >NAN<, result: float(NAN)
>< /= >1<, result: int(0)
>< /= >123<, result: int(0)
>< /= >2e+5<, result: float(0)
>< /= >9223372036854775807<, result: int(0)
-------------------------------------
>< /= >-10<, result: int(0)
>< /= >100<, result: int(0)
>< /= >-34000000000<, result: float(-0)
>< /= >INF<, result: float(0)
>< /= >-INF<, result: float(-0)
>< /= >NAN<, result: float(NAN)
>< /= >1<, result: int(0)
>< /= >123<, result: int(0)
>< /= >2e+5<, result: float(0)
>< /= >9223372036854775807<, result: int(0)
-------------------------------------
>123< /= >-10<, result: float(-12.3)
>123< /= >100<, result: float(1.23)
>123< /= >-34000000000<, result: float(-3.6176470588235E-9)
>123< /= >INF<, result: float(0)
>123< /= >-INF<, result: float(-0)
>123< /= >NAN<, result: float(NAN)
>123< /= >1<, result: int(123)
>123< /= >123<, result: int(1)
>123< /= >2e+5<, result: float(0.000615)
>123< /= >9223372036854775807<, result: float(1.3335686721572E-17)
-------------------------------------
>2e+5< /= >-10<, result: float(-20000)
>2e+5< /= >100<, result: float(2000)
>2e+5< /= >-34000000000<, result: float(-5.8823529411765E-6)
>2e+5< /= >INF<, result: float(0)
>2e+5< /= >-INF<, result: float(-0)
>2e+5< /= >NAN<, result: float(NAN)
>2e+5< /= >1<, result: float(200000)
>2e+5< /= >123<, result: float(1626.0162601626)
>2e+5< /= >2e+5<, result: float(1)
>2e+5< /= >9223372036854775807<, result: float(2.168404344971E-14)
-------------------------------------
>< /= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< /= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< /= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>< /= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>< /= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>< /= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>< /= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< /= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< /= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>< /= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>abc< /= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< /= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< /= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>abc< /= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>abc< /= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(-0)
>abc< /= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(NAN)
>abc< /= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< /= >123<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< /= >2e+5<, result: 
Warning: A non-numeric value encountered in %s on line %d
float(0)
>abc< /= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>9223372036854775807< /= >-10<, result: float(-9.2233720368548E+17)
>9223372036854775807< /= >100<, result: float(9.2233720368548E+16)
>9223372036854775807< /= >-34000000000<, result: float(-271275648.14279)
>9223372036854775807< /= >INF<, result: float(0)
>9223372036854775807< /= >-INF<, result: float(-0)
>9223372036854775807< /= >NAN<, result: float(NAN)
>9223372036854775807< /= >1<, result: int(9223372036854775807)
>9223372036854775807< /= >123<, result: float(7.4986764527275E+16)
>9223372036854775807< /= >2e+5<, result: float(46116860184274)
>9223372036854775807< /= >9223372036854775807<, result: int(1)
-------------------------------------
