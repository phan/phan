<?php

namespace NS737;
use stdClass;

/**
 * @return stdClass
 */
function returns_stdClass() : stdClass {
    return new stdClass();
}
class TestRedundant {

    /** @return stdClass */
    public static function main() : stdClass {
        return new stdClass();
    }
    public static function other() {
        $o = self::main();
        if ($o instanceof stdClass) {
            echo "Fail\n";
        }
        $o2 = returns_stdClass();
        if ($o2 instanceof stdClass)  {
            echo "Fail\n";
        }
    }
}
