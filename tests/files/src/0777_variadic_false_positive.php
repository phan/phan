<?php
namespace NS777;

function test_variadic(...$args) {
    [$id] = $args;
    echo intdiv($id, 2);
}
function has_unknown_variadic($x) {
    test_variadic($x);
    call_user_func('NS777\test_variadic', $x);
}
