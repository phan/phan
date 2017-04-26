<?php

namespace NS278\A {

    const CONST_PUBLIC = 41;

    /** @internal */
    const CONST_INTERNAL = 42;

    /** @internal */
    class C1 {
        const CONST_INTERNAL = 42;
        public $p;
        public function f() {}
    }

    class C2 {
        const CONST_PUBLIC = 41;

        /** @internal */
        const CONST_INTERNAL = 42;

        /** @internal */
        public $p;

        /** @internal */
        public function f() {}

        public function g() {}
    }

    /** @internal */
    function f() {}

    $v01 = f();
    $v02 = CONST_PUBLIC;
    $v03 = CONST_INTERNAL;

    $v1 = new C1;
    $v11 = $v1->p;
    $v12 = $v1->f();
    $v13 = C1::CONST_INTERNAL;

    $v2 = new C2;
    $v21 = $v2->p;
    $v22 = $v2->f();
    $v23 = $v2->g();
    $v24 = C2::CONST_PUBLIC;
    $v25 = C2::CONST_INTERNAL;

    interface I1 {}
    /** @internal */
    interface I2 {}
    trait T1 {}
    /** @internal */
    trait T2 {}

    class C3 extends C1 implements I1, I2 {
        use T1, T2;
        public function __construct() {
            $v01 = f();
            $v02 = CONST_PUBLIC;
            $v03 = CONST_INTERNAL;

            $v1 = new C1;
            $v11 = $v1->p;
            $v12 = $v1->f();
            $v13 = C1::CONST_INTERNAL;

            $v2 = new C2;
            $v21 = $v2->p;
            $v22 = $v2->f();
            $v23 = $v2->g();
            $v24 = C2::CONST_PUBLIC;
            $v25 = C2::CONST_INTERNAL;
        }
    }

}

namespace NS278\B {
    use const NS278\A\CONST_PUBLIC;
    use const NS278\A\CONST_INTERNAL;
    use function NS278\A\f;
    use NS278\A\C1;
    use NS278\A\C2;
    use NS278\A\I1;
    use NS278\A\I2;
    use NS278\A\T1;
    use NS278\A\T2;

    $v01 = f();                             // PhanAccessMethodInternal
    $v02 = CONST_PUBLIC;
    $v03 = CONST_INTERNAL;                  // PhanAccessConstantInternal

    $v1 = new C1;                           // PhanAccessMethodInternal
    $v11 = $v1->p;                          // PhanAccessPropertyInternal
    $v12 = $v1->f();                        // PhanAccessMethodInternal
    $v13 = C1::CONST_INTERNAL;              // PhanAccessClassConstantInternal

    $v2 = new C2;
    $v21 = $v2->p;                          // PhanAccessPropertyInternal
    $v22 = $v2->f();                        // PhanAccessMethodInternal
    $v23 = $v2->g();
    $v24 = C2::CONST_PUBLIC;
    $v25 = C2::CONST_INTERNAL;              // PhanAccessClassConstantInternal

    class C3 extends C1 implements I1, I2 { // PhanAccessClassInternal x 2
        use T1, T2;                         // PhanAccessClassInternal
    }
}
