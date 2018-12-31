<?php
$cb = function (stdClass $x) {
    var_export($x);
};
call_user_func(
    $cb,
    2
);
// Regression test for a bug where Phan failed to warn about incorrect arguments to inline closures.
call_user_func(
    function (stdClass $x) : bool {
        var_export($x);
        return true;
    }
);

