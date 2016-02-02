<?php
class C {

    /** @var DateTime $v */
    function f() {
        if (false) {
            $v = false;
        }
        return $this->g($v);
    }

    function g(DateTime $p) {}
}
