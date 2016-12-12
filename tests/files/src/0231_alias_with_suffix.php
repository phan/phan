<?php
namespace NS1\A {
    class C {}
}

namespace NS2 {
    use NS1\A;

    function f(\NS1\A\C $p) {}
    function g(A\C $p) {}
}