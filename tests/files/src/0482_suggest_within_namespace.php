<?php

namespace NS482\X {
    class Type {
        public static function method() {}
    }

    interface Typi {
        public static function method();
    }

    trait Typt {
        public static function method() {}
    }

    class Kypo {}

    class Abcd {}
}

namespace NS482\Y {
    use NS482\X\Typo;

    Typo::method();
    var_export(new Typo());
}
