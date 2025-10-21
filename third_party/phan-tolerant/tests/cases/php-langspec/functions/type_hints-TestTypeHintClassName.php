/* Auto-generated from php/php-langspec tests */
<?php

class C1 {}
class D1 extends C1 {}

function f2(C1 $p1)
{
    echo "Inside " . __METHOD__ . "\n";

    var_dump($p1);
}

//f2(123); // Argument 1 passed to f1() must be an instance of C1, integer give
//f2([10,20]);    // Argument 1 passed to f2() must be an instance of C1, array given
f2(new C1);
f2(new D1);

