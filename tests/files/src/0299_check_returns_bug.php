<?php

function Example299( int $x ) : bool {
    echo "x = $x\n";
    print "Done printing\n";
}

class X299{
    function helper() : void {}

    function Example( int $x ) : bool {
        // Test for regression where method calls and echo/print statements would mark a function as having a return value.
        self::helper();
        intdiv($x, 2);
        echo "x = ", $x, "\n";
        print "done printing x\n";
    }
}

Example299( 1 );
