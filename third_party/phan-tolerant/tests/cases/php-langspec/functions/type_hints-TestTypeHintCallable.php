/* Auto-generated from php/php-langspec tests */
<?php

function hello()
{
    echo "Hello!\n";
}

function f5(callable $p1)
{
    echo "Inside " . __METHOD__ . "\n";

    var_dump($p1);
    $p1();
}

//f5(123); // must be callable, integer given
f5('hello');

