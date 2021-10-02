<?php

<<<EOT
@phan-type ArraySI = array<string, int>
@phan-type ArrayIS = array<int, string>
EOT;

/**
 * @phan-param ArraySI $data
 * @phan-return ArrayIS
 */
function test1(array $data) {
    return array_flip($data);
}
$result = test1(['key' => 'value']);
'@phan-debug-var $result';

// Should warn
function test2(
    ArraySI $data
): ArrayIS {
    return new ArrayIS();
}
