<?php

namespace NS5985;

// printf-style functions accept null and bool values (PHP declares `mixed ...$values`)
function formatted(?string $a, bool $b, array $arr, \stdClass $o): string {
    printf('%s %d', null, true);
    fprintf(STDERR, '%s', $a);
    echo sprintf('%s', $arr);
    echo sprintf('%s', $o);
    return sprintf('%s %s', $a, $b);
}

// socket_get_status() and socket_set_blocking() are aliases of the stream functions
function streamAliases(): bool {
    $fp = fopen('php://memory', 'r');
    if (!$fp) {
        return false;
    }
    socket_set_blocking($fp, true);
    return socket_get_status($fp)['timed_out'];
}

// Only get_defined_constants(true) groups the constants by extension
function constants(bool $flag): array {
    $a = get_defined_constants();
    $b = get_defined_constants(true);
    $c = get_defined_constants($flag);
    '@phan-debug-var $a, $b, $c';
    return [$a, $b, $c];
}
