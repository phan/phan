<?php

function test_return(array $a) : int
{
    $f = $a['field'] - $a['other'];
    $a = $f - 1;
    echo strlen($a);
    return $a;
}
