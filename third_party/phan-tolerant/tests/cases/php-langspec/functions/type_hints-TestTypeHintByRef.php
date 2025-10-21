/* Auto-generated from php/php-langspec tests */
<?php

function f6(C1 &$p1)
{
    echo "Inside " . __METHOD__ . "\n";

    var_dump($p1);
}

$obj = new C1;
f6($obj);
