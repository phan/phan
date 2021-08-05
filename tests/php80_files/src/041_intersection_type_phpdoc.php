<?php

/**
 * @param callable&object $a
 * @param callable&array<string> $b
 * @param callable&string $c
 */
function test41($a, $b, $c) {
    '@phan-debug-var $a, $b, $c';
    var_dump($a, $b, $c);
    $a();
    $b();
    $c();
}
class A41 {
    public static function method(): void {
        echo "In method\n";
    }
}
test41(function () { echo "."; }, [A41::class, 'method'], 'A41::method');
// wrong arguments
test41([A41::class, 'method'], 'A41::method', function () { echo "."; });
