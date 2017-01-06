<?php
function f253_1(bool $p1) : void {}

// Unfortunately, we only see $v1 as a mixed array
// and as such, can't map individual keys to the
// types at those offsets. The types for `$v2`,
// `$v3`, `$v4`, `$v5`, `$v6`, `$v7`, `$v8`,
// and `$v9` will be unknown.
$v1 = [
    ['k1' => 1, 'k2' => 'string'],
    ['k1' => 2, 'k2' => 'string'],
];
list('k1' => $v2, 'k2' => $v3) = $v1[0];
['k1' => $v4, 'k2' => $v5] = $v1[0];
foreach ($v1 as list('k1' => $v6, 'k2' => $v7)) {
    f253_1($v6);
    f253_1($v7);
}
foreach ($v1 as ['k1' => $v8, 'k2' => $v9]) {
    f253_1($v8);
    f253_1($v9);
}

$v10 = ['k1' => 1, 'k2' => 2];
list('k1' => $v11, 'k2' => $v12) = $v10;
f253_1($v11);
f253_1($v12);

['k1' => $v13, 'k2' => $v14] = $v10;
f253_1($v13);
f253_1($v14);

// Unfortunately, we only see $v15 as a mixed array
// and as such, can't map individual keys to the
// types at those offsets. The types for `$v16` and
// `$v17` will be unknown.
$v15 = ['k1' => 'string', 'k2' => false];
['k1' => $v16, 'k2' => $v17] = $v15;
f253_1($v16);
f253_1($v17);
