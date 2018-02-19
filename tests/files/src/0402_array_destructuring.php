<?php
// list('key' => $a) = ['key' => 42];
list($b) = [42];
list($c) = ['key' => 42];  // should warn
// list('key' => $d) = [42];  // should warn
// list(0 => $e) = ['key' => 42];  // should warn
list(, $b) = [42, 43];
