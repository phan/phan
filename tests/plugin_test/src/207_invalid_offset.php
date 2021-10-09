<?php

$arr = ['string' => 'z', '' => 'x', 0 => []];
function expect_string(string $x) { echo $x; }
expect_string($arr[true]);
expect_string($arr[3.5]);
expect_string($arr[STDIN]);
expect_string($arr[[]]);
expect_string($arr[null]);   // same as $arr['']
expect_string($arr[false]);  // same as $arr[0]
expect_string($arr[true]);
