<?php

$string = "Phan is awesome";

$char1 = $string{-1};
$char2 = $string[-2];
$char3 = $string[-(-2)];
$char4 = $string[-(-(-2))];

const TEST9_NEGATIVE = -1;
$char5 = $string[TEST9_NEGATIVE];

// accessing negative array index should not emit the warning
$array_element = [ -1 => 1 ][-1];
