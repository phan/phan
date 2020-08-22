<?php
/** @phan-file-suppress PhanSyntaxError */
$x = count(
    [2],
);
$cb = function (
    $param1,
    $param2,
) use (
    $x,
) {
    var_dump(
        $param1,
        $param2,
        $x,
    );
}

$cb('a', 'b');
