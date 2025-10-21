--TEST--
PHP Spec test generated from ./expressions/relational_operators/comparisons3.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

///*
// Boolean operand with all kinds of operands, swapping them over to make
// LHS/RHS order is irrelevent.

$oper1 = array(TRUE, FALSE);
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
1 >        0  result: bool(true)
1 >  (bool)0  result: bool(true)
0 <=       1  result: bool(true)
0 <= (bool)1  result: bool(true)
---
1 >=       0  result: bool(true)
1 >= (bool)0  result: bool(true)
0 <        1  result: bool(true)
0 <  (bool)1  result: bool(true)
---
1 <        0  result: bool(false)
1 <  (bool)0  result: bool(false)
0 >=       1  result: bool(false)
0 >= (bool)1  result: bool(false)
---
1 <=       0  result: bool(false)
1 <= (bool)0  result: bool(false)
0 >        1  result: bool(false)
0 >  (bool)1  result: bool(false)
=======
1 >        100  result: bool(false)
1 >  (bool)100  result: bool(false)
100 <=       1  result: bool(true)
100 <= (bool)1  result: bool(true)
---
1 >=       100  result: bool(true)
1 >= (bool)100  result: bool(true)
100 <        1  result: bool(false)
100 <  (bool)1  result: bool(false)
---
1 <        100  result: bool(false)
1 <  (bool)100  result: bool(false)
100 >=       1  result: bool(true)
100 >= (bool)1  result: bool(true)
---
1 <=       100  result: bool(true)
1 <= (bool)100  result: bool(true)
100 >        1  result: bool(false)
100 >  (bool)1  result: bool(false)
=======
1 >        -3.4  result: bool(false)
1 >  (bool)-3.4  result: bool(false)
-3.4 <=       1  result: bool(true)
-3.4 <= (bool)1  result: bool(true)
---
1 >=       -3.4  result: bool(true)
1 >= (bool)-3.4  result: bool(true)
-3.4 <        1  result: bool(false)
-3.4 <  (bool)1  result: bool(false)
---
1 <        -3.4  result: bool(false)
1 <  (bool)-3.4  result: bool(false)
-3.4 >=       1  result: bool(true)
-3.4 >= (bool)1  result: bool(true)
---
1 <=       -3.4  result: bool(true)
1 <= (bool)-3.4  result: bool(true)
-3.4 >        1  result: bool(false)
-3.4 >  (bool)1  result: bool(false)
=======
1 >        1  result: bool(false)
1 >  (bool)1  result: bool(false)
1 <=       1  result: bool(true)
1 <= (bool)1  result: bool(true)
---
1 >=       1  result: bool(true)
1 >= (bool)1  result: bool(true)
1 <        1  result: bool(false)
1 <  (bool)1  result: bool(false)
---
1 <        1  result: bool(false)
1 <  (bool)1  result: bool(false)
1 >=       1  result: bool(true)
1 >= (bool)1  result: bool(true)
---
1 <=       1  result: bool(true)
1 <= (bool)1  result: bool(true)
1 >        1  result: bool(false)
1 >  (bool)1  result: bool(false)
=======
1 >          result: bool(true)
1 >  (bool)  result: bool(true)
 <=       1  result: bool(true)
 <= (bool)1  result: bool(true)
---
1 >=         result: bool(true)
1 >= (bool)  result: bool(true)
 <        1  result: bool(true)
 <  (bool)1  result: bool(true)
---
1 <          result: bool(false)
1 <  (bool)  result: bool(false)
 >=       1  result: bool(false)
 >= (bool)1  result: bool(false)
---
1 <=         result: bool(false)
1 <= (bool)  result: bool(false)
 >        1  result: bool(false)
 >  (bool)1  result: bool(false)
=======
1 >          result: bool(true)
1 >  (bool)  result: bool(true)
 <=       1  result: bool(true)
 <= (bool)1  result: bool(true)
---
1 >=         result: bool(true)
1 >= (bool)  result: bool(true)
 <        1  result: bool(true)
 <  (bool)1  result: bool(true)
---
1 <          result: bool(false)
1 <  (bool)  result: bool(false)
 >=       1  result: bool(false)
 >= (bool)1  result: bool(false)
---
1 <=         result: bool(false)
1 <= (bool)  result: bool(false)
 >        1  result: bool(false)
 >  (bool)1  result: bool(false)
=======
1 >          result: bool(true)
1 >  (bool)  result: bool(true)
 <=       1  result: bool(true)
 <= (bool)1  result: bool(true)
---
1 >=         result: bool(true)
1 >= (bool)  result: bool(true)
 <        1  result: bool(true)
 <  (bool)1  result: bool(true)
---
1 <          result: bool(false)
1 <  (bool)  result: bool(false)
 >=       1  result: bool(false)
 >= (bool)1  result: bool(false)
---
1 <=         result: bool(false)
1 <= (bool)  result: bool(false)
 >        1  result: bool(false)
 >  (bool)1  result: bool(false)
=======
1 >        123  result: bool(false)
1 >  (bool)123  result: bool(false)
123 <=       1  result: bool(true)
123 <= (bool)1  result: bool(true)
---
1 >=       123  result: bool(true)
1 >= (bool)123  result: bool(true)
123 <        1  result: bool(false)
123 <  (bool)1  result: bool(false)
---
1 <        123  result: bool(false)
1 <  (bool)123  result: bool(false)
123 >=       1  result: bool(true)
123 >= (bool)1  result: bool(true)
---
1 <=       123  result: bool(true)
1 <= (bool)123  result: bool(true)
123 >        1  result: bool(false)
123 >  (bool)1  result: bool(false)
=======
1 >        abc  result: bool(false)
1 >  (bool)abc  result: bool(false)
abc <=       1  result: bool(true)
abc <= (bool)1  result: bool(true)
---
1 >=       abc  result: bool(true)
1 >= (bool)abc  result: bool(true)
abc <        1  result: bool(false)
abc <  (bool)1  result: bool(false)
---
1 <        abc  result: bool(false)
1 <  (bool)abc  result: bool(false)
abc >=       1  result: bool(true)
abc >= (bool)1  result: bool(true)
---
1 <=       abc  result: bool(true)
1 <= (bool)abc  result: bool(true)
abc >        1  result: bool(false)
abc >  (bool)1  result: bool(false)
=======

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 22
1 >        Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 23
1 >  (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 24
Array <=       1  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 25
Array <= (bool)1  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 27
1 >=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 28
1 >= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 29
Array <        1  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 30
Array <  (bool)1  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 32
1 <        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 33
1 <  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 34
Array >=       1  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 35
Array >= (bool)1  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 37
1 <=       Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 38
1 <= (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 39
Array >        1  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 40
Array >  (bool)1  result: bool(false)
=======

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 22
1 >        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 23
1 >  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 24
Array <=       1  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 25
Array <= (bool)1  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 27
1 >=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 28
1 >= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 29
Array <        1  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 30
Array <  (bool)1  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 32
1 <        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 33
1 <  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 34
Array >=       1  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 35
Array >= (bool)1  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 37
1 <=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 38
1 <= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 39
Array >        1  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 40
Array >  (bool)1  result: bool(false)
=======
-------------------------------------
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

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 22
 >        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 23
 >  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 24
Array <=         result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 25
Array <= (bool)  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 27
 >=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 28
 >= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 29
Array <          result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 30
Array <  (bool)  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 32
 <        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 33
 <  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 34
Array >=         result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 35
Array >= (bool)  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 37
 <=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 38
 <= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 39
Array >          result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 40
Array >  (bool)  result: bool(false)
=======

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 22
 >        Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 23
 >  (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 24
Array <=         result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 25
Array <= (bool)  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 27
 >=       Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 28
 >= (bool)Array  result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 29
Array <          result: bool(false)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 30
Array <  (bool)  result: bool(false)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 32
 <        Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 33
 <  (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 34
Array >=         result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 35
Array >= (bool)  result: bool(true)
---

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 37
 <=       Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 38
 <= (bool)Array  result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 39
Array >          result: bool(true)

Notice: Array to string conversion in %s/expressions/relational_operators/comparisons3.php on line 40
Array >  (bool)  result: bool(true)
=======
-------------------------------------
