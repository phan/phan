<?php

function test() {
    static $a = null;
    var_export($a ?? ($a = rand(0,10)));  // should not warn about static variables
    $b = null;
    var_export($b ?? ($b = rand(0,10)));
}
