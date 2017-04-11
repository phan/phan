<?php
function f1(callable $callable) {}
f1(function () {});
f1('f1');
class C1 {
    static function f1() {}
}
f1(['C1', 'f1']);
f1([new C1, 'f1']);
