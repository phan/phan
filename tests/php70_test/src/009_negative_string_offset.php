<?php

$string = "Phan is awesome";

$char1 = $string{-1};
$char2 = $string[-2];

// accessing negative array index should not emit the warning
$array_element = [ -1 => 1 ][-1];
