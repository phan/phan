<?php
namespace Foo\Bar {
    class Baz {}
}

namespace Foo {
    class Test {
        /**
         * @return Bar\Baz
         */
        public static function getBaz() {
            return new Bar\Baz;
        }

        /**
         * @return string|int|\Exception
         */
        public static function getException() {
            return new \Exception;
        }
    }
}

namespace Foo2 {
    use Foo\Bar\Baz;

    class Test2 {
        /**
         * @param Baz $baz A baz instance
         */
        public static function takeBaz(Baz $baz) {
            return $baz;
        }

        /**
         * @return Baz[]
         */
        public static function createBazs() {
            return [new Baz, new Baz];
        }
    }
}

namespace {
    $baz = new \Foo\Bar\Baz;
    foreach (\Foo2\Test2::createBazs() as $baz) {
        \Foo2\Test2::takeBaz($baz);
    }
}
