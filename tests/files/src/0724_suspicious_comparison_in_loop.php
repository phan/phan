<?php
function iterate() {
    $j = 0;
    var_export($j < 20);

    $total = 0;
    for ($i = 100; $i < 20; $i++) {$total += $i;}
    for ($i = 0; $i < 10; $i--) { $total += $i; }  // TODO: Warn, this decreases but checks if value is less than something
    $j = 0;
    while ($j == 20) {  // should warn because this is initially false.
        echo "$j Still 20";
        $j++;
    }
    $k = 0;
    while ($k != 20) {  // should not warn because this is initially true
        echo "$k Still not 20";
        $k++;
        // TODO: Warn if nothing in the loop modifies $k?
    }
    return $total;
}
