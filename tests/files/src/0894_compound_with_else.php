<?php

$a = rand(0, 1) === 0 ? new Exception() : NULL;

if (!is_null($a) && count($b = $a->getTrace()) > 0)
{
    '@phan-debug-var $b';
    $c = $b;
}
else
{
    $c = array();
}
