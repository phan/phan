<?php
namespace A {
    function f(Closure $c) {}
    f(function () {});
}

namespace B {
    function f(\Closure $c) {}
    f(function () {});
}

namespace C {
    function f(\Closure $c) {}
    function g(callable $d) { f($d); }
}
