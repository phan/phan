<?php
namespace A {
    const C = 42;
    const D = 24;
}

namespace {
    use \A\{const C, const D};
    function f(int $v) : int {
        return $v;
    }
    $v = f(C);
    $v = f(D);
}
