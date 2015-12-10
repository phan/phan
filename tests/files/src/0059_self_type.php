<?php
namespace A\B;
class Test {
    /** @return self */
    function f() {
        return $this;
    }

    function g(Test $a) {}
}

(new Test)->g((new Test)->f());
