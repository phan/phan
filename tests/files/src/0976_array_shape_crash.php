<?php
$x = ["\x80" => 42];
if (random_int(0, 1) === 0) {
    $x = null;
}
$y = $x["\x80"] ?? null;
'@phan-debug-var $y';
