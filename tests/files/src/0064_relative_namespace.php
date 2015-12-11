<?php
namespace A\B {
    class C {}
    function f(C $v) {}
};

namespace A {
    B\f(new B\C);
};
