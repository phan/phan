<?php

function null_handler(?int $a, ?int $b, string $default, array $values) {
    $a ??= $default;
    echo count($a);
    echo count($b ??= $default);
    echo count($b);
    $missing ??= [2];  // Warn about $missing because it's not in the global scope or a loop
    echo strlen($missing);
    $arr = [null, 2];
    $arr[0] ??= rand(0,10);
    echo strlen($arr);
    foreach ($values as $v) {
        $otherMissing ??= $v;
    }
    echo "Saw $otherMissing\n";
}
// Should not warn
$missing000 ??= 'default';
echo "Setting: $missing000\n";
