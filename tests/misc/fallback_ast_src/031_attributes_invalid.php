<?php  // TODO: Start warning about classes that don't define #[Attribute]
#[MissingAttribute]
function test31(
    #[MissingAttribute2, MissingAttribute3()]
    int $argument
) {
    var_export($argument);
}

#[Attribute]
trait TraitAttribute {}
#[Attribute]
interface InterfaceAttribute {}

// Anything that's used as an attribute must *directly* have an attribute with the built-in Attribute class.
#[Attribute]
class GoodAttribute31 {
}
// This is invalid as an attribute because it does not have the attribute #[Attribute]
class SubGoodAttribute31 extends GoodAttribute31 {}

#[GoodAttribute31]
function goodExample31() {
}

#[SubGoodAttribute31, AbstractAttribute]
function badExample31() {
}
test31(31);
$c = #[TraitAttribute]
    function() {};
$c();

$c2 = #[InterfaceAttribute]
    fn() => true;
$c2();
var_export((new ReflectionFunction('goodExample31'))->getAttributes()[0]->newInstance());
var_export((new ReflectionFunction($c))->getAttributes()[0]->newInstance());

#[Attribute]
abstract class AbstractAttribute {}
