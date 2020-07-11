<?php

declare(strict_types=1);

function test20(?string $x) : string {
    return match(true) {
        default => strlen($x),
        is_string($x) => count($x),
    };
}

function test20b(?string $x) : string {
    return match(true) {
        default => strlen($x),
        is_string($x) => strlen($x),
        is_int($x) => "x=$x",
    };
}

function test20c(?string $x) : string {
    return match(is_string($x)) {
        true => (function() use ($x) { '@phan-debug-var $x'; return $x; })(),
        false => (function() use ($x) { '@phan-debug-var $x'; return $x; })(),
        default => "placeholder",
    };
}
