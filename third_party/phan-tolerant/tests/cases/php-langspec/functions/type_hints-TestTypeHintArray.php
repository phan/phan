/* Auto-generated from php/php-langspec tests */
<?php

function f1(array $p1)
{
    echo "Inside " . __METHOD__ . "\n";

    var_dump($p1);
}

// f1();    // Argument 1 passed to f1() must be of the type array, none given
// f1(123); // Argument 1 passed to f1() must be of the type array, integer given
f1([10,20]);

