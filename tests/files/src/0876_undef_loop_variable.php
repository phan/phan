<?php
function possibly_undef_876(array $a) {
    foreach ($a as $i => $x) {
        echo $i, $x;
    }
    // Phan should warn that $x is possibly undefined because $a is possibly an empty iterable
    echo "\nAfter: i=$i, x=$x\n";
}
possibly_undef_876([2,3]);
