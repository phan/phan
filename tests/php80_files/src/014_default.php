<?php declare(strict_types=1);
// Phan should properly render the default values of various methods
echo base64_decode();
trigger_error();
$x = Closure::bind();
