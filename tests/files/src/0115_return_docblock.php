<?php
namespace NS;
class C {
    /** @return \DateTime */
    private function f() {
        return new \DateTime();
    }
}
(new C)->f()->format('Ymd');
