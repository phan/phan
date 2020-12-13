<?php
function test916(): array {
    $x = [
        null|true,
        null^1,
        null&123,
        // these are runtime errors, Phan should not crash
        ~false,
        ~true,
        ~null,
    ];
    '@phan-debug-var $x';
    return $x;
}
