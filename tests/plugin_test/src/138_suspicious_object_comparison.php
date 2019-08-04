<?php

class C138 {}

function test138() {
    $c1 = new C138();
    $c2 = new C138();
    var_export($c1 == $c2);
    var_export($c1 != $c2);
    var_export($c1 <= $c2);
    var_export($c1 >= $c2);
    var_export($c1 < $c2);
    var_export($c1 > $c2);
    var_export($c1 <=> $c2);
    var_export($c1 | $c2);
}
test138();
