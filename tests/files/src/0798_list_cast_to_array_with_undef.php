<?php

/**
 * @param array{0:string,1?:string} $arr
 */
function y(array $arr):void
{
    var_export($arr);
}

$exploded = explode(",", "one,two");
$limited = array_slice($exploded, 0, 2);
y($limited);
