<?php
function f248_1(bool $p1, bool $p2) {}

$v0 = ['alpha', 'beta'];
list($v1, $v2) = $v0;
f248_1($v1, $v2);

$v3 = [['s1', 's2'], ['s3', 's4']];
foreach ($v3 as list($v4, $v5)) { f248_1($v4, $v5); }
foreach ($v3 as [$v6, $v7]) { f248_1($v6, $v7); }

$v8 = ['k1' => 42, 'k2' => 42];
list('k1' => $v9, 'k2' => $v10) = $v8;
f248_1($v9, $v10);
[ 'k1' => $v11, 'k2' => $v12 ] = $v8;
f248_1($v11, $v12);

$v13 = [$v8];
foreach ($v13 as list('k1' => $v14, 'k2' => $v15)) { f248_1($v14, $v15); }
foreach ($v13 as ['k1' => $v16, 'k2' => $v17]) { f248_1($v16, $v17); }
