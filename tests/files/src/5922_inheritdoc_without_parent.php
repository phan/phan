<?php

interface MyInterface {
    function fromInterface();
}

trait MyTrait {
    function fromTrait() {}
}

class GrandParent implements MyInterface {
    use MyTrait;
    function fromGrandParent() {}
    function fromInterface() {}
}

class ParentClass extends GrandParent {
    function fromParent() {}
}

class ChildClass extends ParentClass {
    /** @inheritDoc */
    function thisOneShouldWarn() {}

    /** @inheritDoc */
    function fromInterface() {}

    /** @inheritDoc */
    function fromTrait() {}

    /** @inheritDoc */
    function fromGrandParent() {}

    /** @inheritDoc */
    function fromParent() {}

    /** {@inheritDoc} */
    function alsoValidInheritDocSyntax() {}

    /** @inheritdoc */
    function caseInsensitiveShouldWarn() {}
}
