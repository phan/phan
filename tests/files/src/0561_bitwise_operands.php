<?php
call_user_func(function (int $a, string $b, array $x, iterable $i) {
    var_export($a | $i);
    var_export($x | $i);
    var_export($a | $x);
    var_export($a | []);
    var_export($a | STDIN);
    var_export($a | 2.3);
    var_export($a | (new stdClass()));
    var_export($a | false);
    var_export($a | $b);
    var_export($a ^ $b);
    var_export($a & $b);
}, 2, 'x', [2], [2]);
