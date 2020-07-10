<?php

namespace NS897;

trait T {
    /**
     * @suppress PhanTypeInvalidThrowsIsInterface
     * @throws \ArrayAccess
     */
    public function test() {
    }

    /**
     * @var MissingClass
     * @suppress PhanUndeclaredTypeProperty
     */
    public $prop;
}
class X1 {
    use T;
}

trait T2 {
    use T;
}

class Y1 {
    use T2;
}
