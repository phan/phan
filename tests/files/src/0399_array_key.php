<?php
/**
 * @param array<string,string> $a
 * @param array<int, string> $b
 * @param array<int, string> $c
 */
function test399(array $a, array $b, array $c) {
    echo $a[0];  // wrong
    echo $a[[]];  // wrong
    echo $b['key'];  // wrong (TODO: Make the error messages for offsets consistent)
    $c['key'] = 'new value';  // should not warn.
    echo $c['key'];  // should not warn.
    echo $b[zend_version()];  // wrong
}
