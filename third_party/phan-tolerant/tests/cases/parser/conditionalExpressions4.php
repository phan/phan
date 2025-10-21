<?php

$a = false ? null : null ?? $b = 3;
var_dump($a, $b);