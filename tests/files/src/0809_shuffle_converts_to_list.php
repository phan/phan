<?php

namespace Card809;

function deal(array $deck) {
    shuffle($deck);
    echo $deck['aces'];
    $deck[] = 'Queen of hearts';
    shuffle($deck);
    if (!$deck) {
        echo "Impossible\n";
        return [];
    }
    return array_slice($deck, 0, 5);
}
function order(array $deck) {
    uasort($deck, 'strcmp');
    var_dump(...$deck);  // should warn
}
