/* Auto-generated from php/php-langspec tests */
<?php

function f3(object $p1)
{
    echo "Inside " . __METHOD__ . "\n";

    var_dump($p1);
}

//f3(123); // Argument 1 passed to f1() must be an instance of object, integer given
//f3([10,20]);    // Argument 1 passed to f2() must be an instance of object, array given
//f3(new C1);         // must be an instance of object, instance of C1 given

// object is not a special/recognized marker in this context

