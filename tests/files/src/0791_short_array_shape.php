<?php
namespace NS781;
use Closure;
/**
 * @param array{Closure():void, int, Closure:Closure():int} $a
 * @param array{stdClass} $b
 * @param array{0} $c
 * @param array{'',array<int,string>,'1'} $c
 */
function test_short_array_shape($a, $b, $c, $d) {
    [$cb, $val] = $a;
    echo spl_object_hash($a['Closure']());
    echo spl_object_hash($cb());
    echo spl_object_hash($b);
    echo spl_object_hash($c);
    echo spl_object_hash($d);
}
