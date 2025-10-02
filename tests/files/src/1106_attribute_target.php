<?php

namespace AttributeTarget;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION)]
class FuncAttr {
}

#[FuncAttr, Attribute]
function test() {echo ".";}

#[FuncAttr()]
class Other {
    #[FuncAttr]
    public const C = 1;
    #[FuncAttr]
    public static $a = 1;
    #[FuncAttr]
    public function b(
        #[FuncAttr]
        int $param
    ) {
        echo self::$a + self::C + $param;
    }
}

#[Attribute()] #[FuncAttr()]
interface I {
}
$c = #[FuncAttr]
    #[Attribute]
    #[FuncAttr]
    function () { echo "."; };
$c();
