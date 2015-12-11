<?php
class C {
    function f() : int {
        return 42;
    }
}
function g(string $i) {}
$v = (new C)->f();
g($v);
