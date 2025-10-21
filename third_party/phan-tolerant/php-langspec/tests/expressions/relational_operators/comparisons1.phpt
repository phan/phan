--TEST--
PHP Spec test generated from ./expressions/relational_operators/comparisons1.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

///*
// checkout the type and value of the result

$a = 10 < 20;
var_dump($a);	// bool(true)
$a = 10 >= 20;
var_dump($a);	// bool(false)
$a = 10 <= "xxx";
var_dump($a);	// bool(false)
$a = "zz" > "xx";
var_dump($a);	// bool(true)
echo "\n";
//*/

///*
// NULL operand with all kinds of operands, swapping them over to make
// LHS/RHS order is irrelevent.

$oper1 = array(NULL);
$oper2 = array(0, 100, -3.4, TRUE, FALSE, NULL, "", "123", "abc", [], [10,2.3]);

foreach ($oper1 as $e1)
{
	foreach ($oper2 as $e2)
	{
		echo "{$e1} >        {$e2}  result: "; var_dump($e1 > $e2);
		echo "{$e1} >  (bool){$e2}  result: "; var_dump($e1 > (bool)$e2);
		echo "{$e2} <=       {$e1}  result: "; var_dump($e2 <= $e1);
		echo "{$e2} <= (bool){$e1}  result: "; var_dump((bool)$e2 <= $e1);
		echo "---\n";
		echo "{$e1} >=       {$e2}  result: "; var_dump($e1 >= $e2);
		echo "{$e1} >= (bool){$e2}  result: "; var_dump($e1 >= (bool)$e2);
		echo "{$e2} <        {$e1}  result: "; var_dump($e2 < $e1);
		echo "{$e2} <  (bool){$e1}  result: "; var_dump((bool)$e2 < $e1);
		echo "---\n";
		echo "{$e1} <        {$e2}  result: "; var_dump($e1 < $e2);
		echo "{$e1} <  (bool){$e2}  result: "; var_dump($e1 < (bool)$e2);
		echo "{$e2} >=       {$e1}  result: "; var_dump($e2 >= $e1);
		echo "{$e2} >= (bool){$e1}  result: "; var_dump((bool)$e2 >= $e1);
		echo "---\n";
		echo "{$e1} <=       {$e2}  result: "; var_dump($e1 <= $e2);
		echo "{$e1} <= (bool){$e2}  result: "; var_dump($e1 <= (bool)$e2);
		echo "{$e2} >        {$e1}  result: "; var_dump($e2 > $e1);
		echo "{$e2} >  (bool){$e1}  result: "; var_dump((bool)$e2 > $e1);
		echo "=======\n";
	}
	echo "-------------------------------------\n";
}
--EXPECTF--
bool(true)
bool(false)
bool(false)
bool(true)

 >        0  result: bool(false)
 >  (bool)0  result: bool(false)
0 <=         result: bool(true)
0 <= (bool)  result: bool(true)
---
 >=       0  result: bool(true)
 >= (bool)0  result: bool(true)
0 <          result: bool(false)
0 <  (bool)  result: bool(false)
---
 <        0  result: bool(false)
 <  (bool)0  result: bool(false)
0 >=         result: bool(true)
0 >= (bool)  result: bool(true)
---
 <=       0  result: bool(true)
 <= (bool)0  result: bool(true)
0 >          result: bool(false)
0 >  (bool)  result: bool(false)
=======
 >        100  result: bool(false)
 >  (bool)100  result: bool(false)
100 <=         result: bool(false)
100 <= (bool)  result: bool(false)
---
 >=       100  result: bool(false)
 >= (bool)100  result: bool(false)
100 <          result: bool(false)
100 <  (bool)  result: bool(false)
---
 <        100  result: bool(true)
 <  (bool)100  result: bool(true)
100 >=         result: bool(true)
100 >= (bool)  result: bool(true)
---
 <=       100  result: bool(true)
 <= (bool)100  result: bool(true)
100 >          result: bool(true)
100 >  (bool)  result: bool(true)
=======
 >        -3.4  result: bool(false)
 >  (bool)-3.4  result: bool(false)
-3.4 <=         result: bool(false)
-3.4 <= (bool)  result: bool(false)
---
 >=       -3.4  result: bool(false)
 >= (bool)-3.4  result: bool(false)
-3.4 <          result: bool(false)
-3.4 <  (bool)  result: bool(false)
---
 <        -3.4  result: bool(true)
 <  (bool)-3.4  result: bool(true)
-3.4 >=         result: bool(true)
-3.4 >= (bool)  result: bool(true)
---
 <=       -3.4  result: bool(true)
 <= (bool)-3.4  result: bool(true)
-3.4 >          result: bool(true)
-3.4 >  (bool)  result: bool(true)
=======
 >        1  result: bool(false)
 >  (bool)1  result: bool(false)
1 <=         result: bool(false)
1 <= (bool)  result: bool(false)
---
 >=       1  result: bool(false)
 >= (bool)1  result: bool(false)
1 <          result: bool(false)
1 <  (bool)  result: bool(false)
---
 <        1  result: bool(true)
 <  (bool)1  result: bool(true)
1 >=         result: bool(true)
1 >= (bool)  result: bool(true)
---
 <=       1  result: bool(true)
 <= (bool)1  result: bool(true)
1 >          result: bool(true)
1 >  (bool)  result: bool(true)
=======
 >          result: bool(false)
 >  (bool)  result: bool(false)
 <=         result: bool(true)
 <= (bool)  result: bool(true)
---
 >=         result: bool(true)
 >= (bool)  result: bool(true)
 <          result: bool(false)
 <  (bool)  result: bool(false)
---
 <          result: bool(false)
 <  (bool)  result: bool(false)
 >=         result: bool(true)
 >= (bool)  result: bool(true)
---
 <=         result: bool(true)
 <= (bool)  result: bool(true)
 >          result: bool(false)
 >  (bool)  result: bool(false)
=======
 >          result: bool(false)
 >  (bool)  result: bool(false)
 <=         result: bool(true)
 <= (bool)  result: bool(true)
---
 >=         result: bool(true)
 >= (bool)  result: bool(true)
 <          result: bool(false)
 <  (bool)  result: bool(false)
---
 <          result: bool(false)
 <  (bool)  result: bool(false)
 >=         result: bool(true)
 >= (bool)  result: bool(true)
---
 <=         result: bool(true)
 <= (bool)  result: bool(true)
 >          result: bool(false)
 >  (bool)  result: bool(false)
=======
 >          result: bool(false)
 >  (bool)  result: bool(false)
 <=         result: bool(true)
 <= (bool)  result: bool(true)
---
 >=         result: bool(true)
 >= (bool)  result: bool(true)
 <          result: bool(false)
 <  (bool)  result: bool(false)
---
 <          result: bool(false)
 <  (bool)  result: bool(false)
 >=         result: bool(true)
 >= (bool)  result: bool(true)
---
 <=         result: bool(true)
 <= (bool)  result: bool(true)
 >          result: bool(false)
 >  (bool)  result: bool(false)
=======
 >        123  result: bool(false)
 >  (bool)123  result: bool(false)
123 <=         result: bool(false)
123 <= (bool)  result: bool(false)
---
 >=       123  result: bool(false)
 >= (bool)123  result: bool(false)
123 <          result: bool(false)
123 <  (bool)  result: bool(false)
---
 <        123  result: bool(true)
 <  (bool)123  result: bool(true)
123 >=         result: bool(true)
123 >= (bool)  result: bool(true)
---
 <=       123  result: bool(true)
 <= (bool)123  result: bool(true)
123 >          result: bool(true)
123 >  (bool)  result: bool(true)
=======
 >        abc  result: bool(false)
 >  (bool)abc  result: bool(false)
abc <=         result: bool(false)
abc <= (bool)  result: bool(false)
---
 >=       abc  result: bool(false)
 >= (bool)abc  result: bool(false)
abc <          result: bool(false)
abc <  (bool)  result: bool(false)
---
 <        abc  result: bool(true)
 <  (bool)abc  result: bool(true)
abc >=         result: bool(true)
abc >= (bool)  result: bool(true)
---
 <=       abc  result: bool(true)
 <= (bool)abc  result: bool(true)
abc >          result: bool(true)
abc >  (bool)  result: bool(true)
=======

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 36
 >        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 37
 >  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 38
Array <=         result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 39
Array <= (bool)  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 41
 >=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 42
 >= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 43
Array <          result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 44
Array <  (bool)  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 46
 <        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 47
 <  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 48
Array >=         result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 49
Array >= (bool)  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 51
 <=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 52
 <= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 53
Array >          result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 54
Array >  (bool)  result: bool(false)
=======

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 36
 >        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 37
 >  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 38
Array <=         result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 39
Array <= (bool)  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 41
 >=       Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 42
 >= (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 43
Array <          result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 44
Array <  (bool)  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 46
 <        Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 47
 <  (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 48
Array >=         result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 49
Array >= (bool)  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 51
 <=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 52
 <= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 53
Array >          result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons1.php on line 54
Array >  (bool)  result: bool(true)
=======
-------------------------------------
