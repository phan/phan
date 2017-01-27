<?php
namespace NS257;
class C1 { }
class C2 {
    /** @var C1|bool */
    public $p;
    function f1() {
        $v1 = $this->p ?: null;
        $this->f2($v1);
    }
    function f2(string $p) {}
}
