<?php
list('foo' => $a, 'bar' => $b) = $x;
[$a, $b] = $x;
['foo' => $a, 'bar' => $b] = $x;
[, [$a]] = $x;
