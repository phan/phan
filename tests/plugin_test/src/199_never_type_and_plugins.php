<?php

function exitMisuse(?int $x, bool $z): array {
    $x1 = $x ?: exit('expected nonzero');
    $z2 = $z || exit(true);
    $y = $z ?: exit('expected nonzero');
    $y3 = $x ?? exit('fail');
    '@phan-debug-var $y, $x1, $z2, $y3'; // Not worth specializing $z2 right now, using the return type would be uncommon
    $y2 = $x + exit('fail');
    return [$x1, $z2, $y2, $y3];
}

function up(string $message): never {
    $x = exit($message);
}
throw up('goodbye');
