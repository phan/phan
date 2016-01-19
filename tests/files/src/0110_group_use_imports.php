<?php

namespace test {
    class C {}
    function f() {}
};

namespace {
    use test\{C};
    use test\{function f};

    new C();
    f();
};

