<?php

/**
 * @param callable&object $a
 * @param callable&array $ba
 * @param callable&array<string> $bb
 * @param callable&array<mixed> $bc
 * @param callable&string $c
 */
function test41a($a, $ba, $bb, $bc, $c) {
    '@phan-debug-var $a, $ba, $bb, $bc, $c';
    var_dump($a, $ba, $bb, $bc, $c);
    $a(); $ba(); $bb(); $bc(); $c();
}
/**
 * @param object&callable $a
 * @param array&callable $ba
 * @param array<string>&callable $bb
 * @param array<mixed>&callable $bc
 * @param string&callable $c
 */
function test41b($a, $ba, $bb, $bc, $c) {
    '@phan-debug-var $a, $ba, $bb, $bc, $c';
    var_dump($a, $ba, $bb, $bc, $c);
    $a(); $ba(); $bb(); $bc(); $c();
}
class A41 {
    public static function method(): void {
        echo "In method\n";
    }
}
test41a(function () { echo "."; }, [A41::class, 'method'], [A41::class, 'method'], [new A41, 'method'], 'A41::method');
test41b(function () { echo "."; }, [A41::class, 'method'], [A41::class, 'method'], [new A41, 'method'], 'A41::method');
// wrong arguments
test41a([A41::class, 'method'], 'A41::method', [new A41, 'method'], 'A41::method', function () { echo "."; });
test41b([A41::class, 'method'], 'A41::method', [new A41, 'method'], 'A41::method', function () { echo "."; });
