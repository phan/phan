<?php
declare(strict_types=1);

$x = [];
$obj = new stdClass();
echo intdiv($obj::class, 2);
$value = $x::class;
if (is_string($value)) {
    echo "Not possible\n";
}
