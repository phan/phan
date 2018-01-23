<?php
['key' => $a] = ['key' => 42];
[$b] = [42];
[$c] = ['key' => 42];  // should warn
['key' => $d] = [42];  // should warn
[0 => $e] = ['key' => 42];  // should warn
