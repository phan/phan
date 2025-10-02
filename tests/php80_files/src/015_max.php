<?php

declare(strict_types=1);

// Should avoid false positives about argument types with strict_types=1
function test15(int $b): int {
    return max($b, 0);
}
/** @return array{0:bool,1:bool} */
function test15b(?bool $b): array {
    return [is_bool($b), is_null($b)];
}
function test16c(mixed $m, string $s, float $f, object $o, array $a, iterable $i, callable $c): array {
    return [
        max($m, 'a'),
        max($s, 'a'),
        max($f, 0.0),
        max($o, new stdClass()),
        max($a, [1]),
        max($i, [1]),
        max($c, 'is_string'),
    ];
}
