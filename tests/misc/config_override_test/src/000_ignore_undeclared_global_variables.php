<?php

class A
{
    /** @var string */
    public $b = 'value';

    /** @var bool */
    public $boolVal = true;
}

assert($a instanceof A);

echo strlen($a->b);
if ($a->boolVal) {}
if ($a->b) {}

if ($myVar instanceof A) {
    echo intdiv($myVar->b, 2);
    if ($myVar->boolVal) {}
    if ($myVar->b) {}
}
