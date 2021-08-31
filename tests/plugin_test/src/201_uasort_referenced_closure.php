<?php

$array = [
    ["x" => "xyz"],
    ["x" => "def"],
    ["x" => "abc"],
];

uasort(
    $array,
    /**
     * @param array<string,string> $a
     * @param array<string,string> $b
     */
    static function (array $a, array $b): int { return $a["x"] <=> $b["x"]; }
);

function test201($a, $b) {
    return $a <=> $b;
}
$values = [1,3,2];
uasort($values, 'test201');
