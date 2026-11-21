<?php

// A class name with a namespace that is not fully qualified is relative to the current namespace:
// `Sub\Base` in the namespace `NS5962` is \NS5962\Sub\Base, not \Sub\Base.

namespace NS5962\Sub {
    class Base {
        /** @var \stdClass */
        protected static $prop;
    }
}

namespace Sub {
    class Base {
        /** @var \stdClass */
        public static $prop;
    }
}

namespace NS5962 {
    use NS5962\Sub as SubAlias;

    class Child extends Sub\Base {
        public static function getAfterQualifiedName(): \stdClass {
            if (self::$prop === null) {
                self::setWithQualifiedName();
            }
            return self::$prop;
        }

        public static function getAfterAlias(): \stdClass {
            if (self::$prop === null) {
                self::setWithAlias();
            }
            return self::$prop;
        }

        public static function getAfterNamespaceRelativeName(): \stdClass {
            if (self::$prop === null) {
                self::setWithNamespaceRelativeName();
            }
            return self::$prop;
        }

        public static function getAfterSettingGlobalClass(): \stdClass {
            if (self::$prop === null) {
                self::setGlobalClass();
            }
            return self::$prop;  // Should warn: \Sub\Base::$prop is another property.
        }

        private static function setWithQualifiedName(): void {
            Sub\Base::$prop = new \stdClass();
        }

        private static function setWithAlias(): void {
            SubAlias\Base::$prop = new \stdClass();
        }

        private static function setWithNamespaceRelativeName(): void {
            namespace\Sub\Base::$prop = new \stdClass();
        }

        private static function setGlobalClass(): void {
            \Sub\Base::$prop = new \stdClass();
        }
    }
}
