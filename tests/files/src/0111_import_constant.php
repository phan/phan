<?php
namespace A111 {
    const C = 42;
    const D = 24;
    const Foo = 24;
    const bar = 24;
}

namespace {
    use \A111\{const C, const D};
    use const \A111\Foo;
    use \A111\{const Bar};  // Import using the wrong case for the constant name
    use \A111\{const UndeclaredConst};
    function f(int $v) : int {
        return $v;
    }
    $v = f(C);
    $v = f(D);
    $v = f(c);  // undefined
    $v = f(Foo);
    $v = f(foo);  // also undefined
    $v = f(Bar);
    $v = f(UndeclaredConst);
}
