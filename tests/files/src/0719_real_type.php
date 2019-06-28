<?php
function testCast719($x) {
    $a = $x ?? [true];
    echo intdiv($a, 2);
    [$b] = $a;
    if ($b == false) {  // should not emit PhanRedundantCondition since the real type of $b isn't inferable
        echo "Saw b\n";
    }
}

function testCast719Accurate() {
    $x = rand() % 2 ? [true, 'a'] : null;
    $a = $x ?? [true, 'b'];
    echo intdiv($a, 2);
    [$b, $details] = $a;
    if ($b == false) {  // should emit PhanRedundantCondition since the real type of $b is inferable
        echo "Saw b details=$details\n";
    }
}
