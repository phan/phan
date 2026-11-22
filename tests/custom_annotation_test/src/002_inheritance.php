<?php

// getCustomAnnotations() of an inherited copy of a member, or of a copy imported from a trait,
// returns the annotations of the declaration. The plugin is only called for the declarations.

class BaseWithMarks
{
    /** @phan-x-test-mark on a base constant */
    public const INHERITED = 1;

    /** @phan-x-test-mark on a base property */
    public $inherited_property = 1;

    /** @phan-x-test-mark on a base static property */
    public static $inherited_static = 2;

    /** @phan-x-test-mark on a base method */
    public function inheritedMethod(): void
    {
    }

    /** @phan-x-test-mark on a base method that the child overrides */
    public function overridden(): void
    {
    }
}

class ChildWithoutMarks extends BaseWithMarks
{
    // The declaration in the child has no custom annotation.
    public function overridden(): void
    {
    }
}

class GrandChild extends ChildWithoutMarks
{
}

/** @phan-x-test-mark on a trait with members */
trait MarkedMembers
{
    /** @phan-x-test-mark on a trait constant */
    public const TRAIT_CONSTANT = 2;

    /** @phan-x-test-mark on a trait property */
    public $trait_property = 1;

    /** @phan-x-test-mark on a private trait property */
    private $private_trait_property = 1;

    /** @phan-x-test-mark on a trait method */
    public function traitMethod(): int
    {
        return $this->private_trait_property;
    }

    /** @phan-x-test-mark on a static trait method */
    public static function staticTraitMethod(): void
    {
    }
}

trait NestedTrait
{
    use MarkedMembers;
}

class UsesTrait
{
    use MarkedMembers;

    public function readPrivate(): int
    {
        // The copy of the private property in this class has this class as its defining FQSEN.
        return $this->private_trait_property;
    }
}

class UsesNestedTrait
{
    use NestedTrait {
        traitMethod as aliasedTraitMethod;
    }
}

// ExternalBase is in external/, which is excluded from analysis: with lazy_inherited_methods, the copies of its methods
// in LocalChild are created when they are first looked up.
class LocalChild extends ExternalBase
{
}

function use_inherited(ChildWithoutMarks $c, GrandChild $g, UsesTrait $t, UsesNestedTrait $n, LocalChild $l): void
{
    $c->inheritedMethod();
    $c->overridden();
    $g->inheritedMethod();
    $g->overridden();
    echo $c->inherited_property, ChildWithoutMarks::$inherited_static, $g->inherited_property;
    echo ChildWithoutMarks::INHERITED, GrandChild::INHERITED;
    echo $t->traitMethod(), $t->readPrivate();
    echo $t->trait_property;
    echo UsesTrait::TRAIT_CONSTANT;
    UsesTrait::staticTraitMethod();
    echo $n->traitMethod(), $n->aliasedTraitMethod();
    echo $n->trait_property, UsesNestedTrait::TRAIT_CONSTANT;
    $l->externalMethod();
    $l->unmarkedExternalMethod();
    echo $l->external_property, LocalChild::EXTERNAL_CONSTANT;
}
