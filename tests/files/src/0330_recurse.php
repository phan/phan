<?php

function callSelf(bool $x = false) {
    if (!$x) {
        callSelf(true);
        callSelf(42);  // should warn about expecting **bool**
        echo intdiv($x, 20);
    }
}

class A330 {
    function callSelf(bool $x = false) {
        if (!$x) {
            self::callSelf(true);
            A330::callSelf(true);
            self::callSelf(42);  // should warn about expecting **bool**
            echo intdiv($x, 20);
        }
    }
}

class B330 extends A330 {
}
$a330 = new A330();
$a330->callSelf();
$b330 = new B330();
$b330->callSelf();
