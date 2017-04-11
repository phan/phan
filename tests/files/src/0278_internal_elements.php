<?php

namespace NS278\A {
    /** @internal */
    class C1 {
        public $p;
        public function f() {}
        public function g() {}
    }

    class C2 {
        /** @internal */
        public $p;

        /** @internal */
        public function f() {}

        public function g() {}
    }

    $v1 = new C1;
    $v11 = $v1->p;
    $v12 = $v1->f();
    $v13 = $v1->g();

    $v2 = new C2;
    $v21 = $v2->p;
    $v22 = $v2->f();
    $v23 = $v2->g();

    class C3 {
        public function __construct() {
            $v1 = new C1;
            $v11 = $v1->p;
            $v12 = $v1->f();
            $v13 = $v1->g();

            $v2 = new C2;
            $v21 = $v2->p;
            $v22 = $v2->f();
            $v23 = $v2->g();
        }
    }
}

namespace NS278\B {
    use NS278\A\C1;
    use NS278\A\C2;

    $v1 = new C1;     // PhanAccessMethodInternal
    $v11 = $v1->p;    // PhanAccessPropertyInternal
    $v12 = $v1->f();  // PhanAccessMethodInternal
    $v13 = $v1->g();  // PhanAccessMethodInternal

    $v2 = new C2;
    $v21 = $v2->p;    // PhanAccessPropertyInternal
    $v22 = $v2->f();  // PhanAccessMethodInternal
    $v23 = $v2->g();
}
