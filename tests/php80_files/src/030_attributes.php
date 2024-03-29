<?php

namespace AttributeTests;

use Attribute;


#[Attribute(Attribute::TARGET_ALL | Attribute::IS_REPEATABLE)]
class MyAttribute {
    public function __construct(int $x = 0) {}
}

#[MyAttribute]
class X {
    #[MyAttribute(1), MyAttribute(2)]
    public $property;

    #[MyAttribute,]
    public static function test(): int {
        return 2;
    }

    #[MyAttribute]
    protected const FOO = 123;
}
echo X::test();

$cb = #[MyAttribute()]
    fn(
        #[namespace\MyAttribute(MISSING_CONSTANT)]
        int $x
    ) => $x * 2 + 1;
$cb2 = #[MyAttribute(),]
    fn() => 2;
$cb(1);
$cb2();
