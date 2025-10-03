<?php
class test31 {
    public float $x = 0;
    public float $y = 0.0;
    public float $z = '0';
    public float $w = null;
    public ?float $w2 = null;
}

$y = new test31;
// Setting $x to an int will not raise any warnings
$y->x = 5;
var_dump($y->x);
