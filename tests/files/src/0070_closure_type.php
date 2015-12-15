<?php
namespace A {
    function f(Closure $c) {}
    f(function () {});
}

namespace B {
    function f(\Closure $c) {}
    f(function () {});
}

