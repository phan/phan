<?php
function f(Closure $closure = null) {
    $array = [3, 2, 1];
    usort($array, $closure);
}
f(function($a, $b) { return $a <=> $b; });
