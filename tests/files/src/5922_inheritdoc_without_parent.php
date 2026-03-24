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

    /**
     * This method mentions @inheritDocumentation in prose.
     * The word boundary should prevent matching that.
     */
    function noWarnOnProse() {}
}

// Trait methods with @inheritDoc should not warn, even if the trait
// itself doesn't extend or implement anything. Traits are composed
// into classes that may implement interfaces declaring these methods.
interface TraitTargetInterface {
    /** Interface method doc. */
    function interfaceMethod(): void;
}

trait TraitWithInheritDoc {
    /** @inheritDoc */
    function interfaceMethod(): void {}  // should NOT warn

    /** @inheritDoc */
    function traitOnlyMethod(): void {}  // should NOT warn
}

class UsesTraitAndInterface implements TraitTargetInterface {
    use TraitWithInheritDoc;
}
