<?php
trait T {
    static function t1(int $arg1, string $arg2="abc") {
        return $arg1;
    }
}

class C {
    use T;
}

C::unknown();
C::t1();
C::t1(1,2,3);
C::t1(1.5);
