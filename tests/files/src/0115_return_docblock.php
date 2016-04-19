<?php
namespace NS;
class C {
    /** @return \DateTime */
    function f() {
        return new \DateTime();
    }
}
(new C)->f()->format('Ymd');
