/* Auto-generated from php/php-langspec tests */
<?php

interface I1 {}
interface I2 extends I1 {}
class C2 implements I1 {}
class D2 extends C2 implements I2 {}

function f4(I1 $p1)
{
    echo "Inside " . __METHOD__ . "\n";

    var_dump($p1);
}

//f4(123); // must implement interface I1, integer given
f4(new C2);
f4(new D2);

