<?php
function test158(array $a, array $b) {
    foreach ($a as $i => $elem) {
        echo "Saw $i, $elem\n";
        foreach ($b as $i => $elem) {
            echo "Inner processed $i, $elem\n";
        }
        echo "Done with $i, $elem\n";
    }
    for ($j = 0; $j < 10; $j++) {
        // various code or scopes....
        foreach ($a as $j => $value) {
            echo "Processing $j, $value\n";
        }
    }
    $j = 0;
    while ($j < 10) {
        foreach ($b as $x) {
            // various code or scopes....
            foreach ($a as $j => $value) {
                echo "Processing $x, $j, $value\n";
            }
        }
        $j++;
    }
}
test158([1], [2]);
