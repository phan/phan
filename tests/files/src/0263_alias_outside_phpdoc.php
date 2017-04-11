<?php

// This is completely invalid, classes instead of native types
function foo263(boolean $a, integer $b, callback $c, double $d, double ...$e) : boolean {
    return false;
}
foo263(false, 2, function() {}, 1.2345, 3.14);
