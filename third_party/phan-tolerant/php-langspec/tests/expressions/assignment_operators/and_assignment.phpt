--TEST--
&= operator
--FILE--
<?php

require __DIR__ . '/common.inc';

foreach ($oper as $t)
{
	foreach ($oper as $e2)
	{
		$e1 = $t;
		echo ">$e1< &= >$e2<, result: "; var_dump($e1 &= $e2);
	}
	echo "-------------------------------------\n";
}

?>
--EXPECTF--
>0< &= >0<, result: int(0)
>0< &= >-10<, result: int(0)
>0< &= >100<, result: int(0)
>0< &= >-34000000000<, result: int(0)
>0< &= >INF<, result: int(0)
>0< &= >-INF<, result: int(0)
>0< &= >NAN<, result: int(0)
>0< &= >1<, result: int(0)
>0< &= ><, result: int(0)
>0< &= ><, result: int(0)
>0< &= >123<, result: int(0)
>0< &= >2e+5<, result: int(0)
>0< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>0< &= >9223372036854775807<, result: int(0)
-------------------------------------
>-10< &= >0<, result: int(0)
>-10< &= >-10<, result: int(-10)
>-10< &= >100<, result: int(100)
>-10< &= >-34000000000<, result: int(-34000000000)
>-10< &= >INF<, result: int(0)
>-10< &= >-INF<, result: int(0)
>-10< &= >NAN<, result: int(0)
>-10< &= >1<, result: int(0)
>-10< &= ><, result: int(0)
>-10< &= ><, result: int(0)
>-10< &= >123<, result: int(114)
>-10< &= >2e+5<, result: int(200000)
>-10< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-10< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-10< &= >9223372036854775807<, result: int(9223372036854775798)
-------------------------------------
>100< &= >0<, result: int(0)
>100< &= >-10<, result: int(100)
>100< &= >100<, result: int(100)
>100< &= >-34000000000<, result: int(0)
>100< &= >INF<, result: int(0)
>100< &= >-INF<, result: int(0)
>100< &= >NAN<, result: int(0)
>100< &= >1<, result: int(0)
>100< &= ><, result: int(0)
>100< &= ><, result: int(0)
>100< &= >123<, result: int(96)
>100< &= >2e+5<, result: int(64)
>100< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>100< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>100< &= >9223372036854775807<, result: int(100)
-------------------------------------
>-34000000000< &= >0<, result: int(0)
>-34000000000< &= >-10<, result: int(-34000000000)
>-34000000000< &= >100<, result: int(0)
>-34000000000< &= >-34000000000<, result: int(-34000000000)
>-34000000000< &= >INF<, result: int(0)
>-34000000000< &= >-INF<, result: int(0)
>-34000000000< &= >NAN<, result: int(0)
>-34000000000< &= >1<, result: int(0)
>-34000000000< &= ><, result: int(0)
>-34000000000< &= ><, result: int(0)
>-34000000000< &= >123<, result: int(0)
>-34000000000< &= >2e+5<, result: int(68608)
>-34000000000< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-34000000000< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-34000000000< &= >9223372036854775807<, result: int(9223372002854775808)
-------------------------------------
>INF< &= >0<, result: int(0)
>INF< &= >-10<, result: int(0)
>INF< &= >100<, result: int(0)
>INF< &= >-34000000000<, result: int(0)
>INF< &= >INF<, result: int(0)
>INF< &= >-INF<, result: int(0)
>INF< &= >NAN<, result: int(0)
>INF< &= >1<, result: int(0)
>INF< &= ><, result: int(0)
>INF< &= ><, result: int(0)
>INF< &= >123<, result: int(0)
>INF< &= >2e+5<, result: int(0)
>INF< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>INF< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>INF< &= >9223372036854775807<, result: int(0)
-------------------------------------
>-INF< &= >0<, result: int(0)
>-INF< &= >-10<, result: int(0)
>-INF< &= >100<, result: int(0)
>-INF< &= >-34000000000<, result: int(0)
>-INF< &= >INF<, result: int(0)
>-INF< &= >-INF<, result: int(0)
>-INF< &= >NAN<, result: int(0)
>-INF< &= >1<, result: int(0)
>-INF< &= ><, result: int(0)
>-INF< &= ><, result: int(0)
>-INF< &= >123<, result: int(0)
>-INF< &= >2e+5<, result: int(0)
>-INF< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-INF< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>-INF< &= >9223372036854775807<, result: int(0)
-------------------------------------
>NAN< &= >0<, result: int(0)
>NAN< &= >-10<, result: int(0)
>NAN< &= >100<, result: int(0)
>NAN< &= >-34000000000<, result: int(0)
>NAN< &= >INF<, result: int(0)
>NAN< &= >-INF<, result: int(0)
>NAN< &= >NAN<, result: int(0)
>NAN< &= >1<, result: int(0)
>NAN< &= ><, result: int(0)
>NAN< &= ><, result: int(0)
>NAN< &= >123<, result: int(0)
>NAN< &= >2e+5<, result: int(0)
>NAN< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>NAN< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>NAN< &= >9223372036854775807<, result: int(0)
-------------------------------------
>1< &= >0<, result: int(0)
>1< &= >-10<, result: int(0)
>1< &= >100<, result: int(0)
>1< &= >-34000000000<, result: int(0)
>1< &= >INF<, result: int(0)
>1< &= >-INF<, result: int(0)
>1< &= >NAN<, result: int(0)
>1< &= >1<, result: int(1)
>1< &= ><, result: int(0)
>1< &= ><, result: int(0)
>1< &= >123<, result: int(1)
>1< &= >2e+5<, result: int(0)
>1< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>1< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>1< &= >9223372036854775807<, result: int(1)
-------------------------------------
>< &= >0<, result: int(0)
>< &= >-10<, result: int(0)
>< &= >100<, result: int(0)
>< &= >-34000000000<, result: int(0)
>< &= >INF<, result: int(0)
>< &= >-INF<, result: int(0)
>< &= >NAN<, result: int(0)
>< &= >1<, result: int(0)
>< &= ><, result: int(0)
>< &= ><, result: int(0)
>< &= >123<, result: int(0)
>< &= >2e+5<, result: int(0)
>< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >9223372036854775807<, result: int(0)
-------------------------------------
>< &= >0<, result: int(0)
>< &= >-10<, result: int(0)
>< &= >100<, result: int(0)
>< &= >-34000000000<, result: int(0)
>< &= >INF<, result: int(0)
>< &= >-INF<, result: int(0)
>< &= >NAN<, result: int(0)
>< &= >1<, result: int(0)
>< &= ><, result: int(0)
>< &= ><, result: int(0)
>< &= >123<, result: int(0)
>< &= >2e+5<, result: int(0)
>< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >9223372036854775807<, result: int(0)
-------------------------------------
>123< &= >0<, result: int(0)
>123< &= >-10<, result: int(114)
>123< &= >100<, result: int(96)
>123< &= >-34000000000<, result: int(0)
>123< &= >INF<, result: int(0)
>123< &= >-INF<, result: int(0)
>123< &= >NAN<, result: int(0)
>123< &= >1<, result: int(1)
>123< &= ><, result: int(0)
>123< &= ><, result: int(0)
>123< &= >123<, result: string(3) "123"
>123< &= >2e+5<, result: string(3) "0 #"
>123< &= ><, result: string(0) ""
>123< &= >abc<, result: string(3) "!"#"
>123< &= >9223372036854775807<, result: int(123)
-------------------------------------
>2e+5< &= >0<, result: int(0)
>2e+5< &= >-10<, result: int(200000)
>2e+5< &= >100<, result: int(64)
>2e+5< &= >-34000000000<, result: int(68608)
>2e+5< &= >INF<, result: int(0)
>2e+5< &= >-INF<, result: int(0)
>2e+5< &= >NAN<, result: int(0)
>2e+5< &= >1<, result: int(0)
>2e+5< &= ><, result: int(0)
>2e+5< &= ><, result: int(0)
>2e+5< &= >123<, result: string(3) "0 #"
>2e+5< &= >2e+5<, result: string(4) "2e+5"
>2e+5< &= ><, result: string(0) ""
>2e+5< &= >abc<, result: string(3) " `#"
>2e+5< &= >9223372036854775807<, result: int(200000)
-------------------------------------
>< &= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>< &= >123<, result: string(0) ""
>< &= >2e+5<, result: string(0) ""
>< &= ><, result: string(0) ""
>< &= >abc<, result: string(0) ""
>< &= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>abc< &= >0<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >-10<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >100<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >-34000000000<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >-INF<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >NAN<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >1<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>abc< &= >123<, result: string(3) "!"#"
>abc< &= >2e+5<, result: string(3) " `#"
>abc< &= ><, result: string(0) ""
>abc< &= >abc<, result: string(3) "abc"
>abc< &= >9223372036854775807<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
-------------------------------------
>9223372036854775807< &= >0<, result: int(0)
>9223372036854775807< &= >-10<, result: int(9223372036854775798)
>9223372036854775807< &= >100<, result: int(100)
>9223372036854775807< &= >-34000000000<, result: int(9223372002854775808)
>9223372036854775807< &= >INF<, result: int(0)
>9223372036854775807< &= >-INF<, result: int(0)
>9223372036854775807< &= >NAN<, result: int(0)
>9223372036854775807< &= >1<, result: int(1)
>9223372036854775807< &= ><, result: int(0)
>9223372036854775807< &= ><, result: int(0)
>9223372036854775807< &= >123<, result: int(123)
>9223372036854775807< &= >2e+5<, result: int(200000)
>9223372036854775807< &= ><, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>9223372036854775807< &= >abc<, result: 
Warning: A non-numeric value encountered in %s on line %d
int(0)
>9223372036854775807< &= >9223372036854775807<, result: int(9223372036854775807)
-------------------------------------
