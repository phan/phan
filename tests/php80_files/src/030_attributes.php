<?php
namespace AttributeTests;
// XXX: Phan will need to upgrade from AST version 70 to 80 before it can parse attributes.
// So it isn't actually checking the attributes right now.
#[Attribute]
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
