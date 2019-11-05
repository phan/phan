<?php

namespace NS814;

function test814(array $a, array $b, string $str) {
    if (array_key_exists('foo', $a)) {
        if ($a) {  // this is redundant
            echo "A is non-empty\n";
        }
    }
    if (array_key_exists($str, $b)) {
        if (!$b) {  // this is impossible
            echo "A is empty\n";
        }
    }
}
