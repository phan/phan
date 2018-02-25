<?php

namespace NS2 {
    class C {
        public function f() {
            echo __CLASS__;
        }
    }
}

namespace NS2\C {
    class D {
        const STATUS = 1;
        public function f() {
            echo __CLASS__;
        }
    }
}

namespace NS {
    use NS2\C;
    class A {
        public function f() {
            echo C\D::STATUS;
        }
    }
}
