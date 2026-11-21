<?php

namespace NS5961;

class BaseClass {}

abstract class AbstractBase {}

interface FooInterface {}

trait OtherTrait {}

// Valid targets.

/** @require-extends BaseClass */
trait RequiresClass {}

/** @require-extends AbstractBase */
trait RequiresAbstractClass {}

/** @require-extends \Exception */
trait RequiresInternalClass {}

/** @require-implements FooInterface */
trait RequiresInterface {}

/** @psalm-require-implements \Countable */
trait RequiresInternalInterface {}

// The target of @require-extends is not a class.

/** @require-extends FooInterface */
trait RequiresExtendingInterface {}

/** @psalm-require-extends OtherTrait */
trait RequiresExtendingTrait {}

/** @require-extends \Countable */
trait RequiresExtendingInternalInterface {}

// The target of @require-implements is not an interface.

/** @require-implements BaseClass */
trait RequiresImplementingClass {}

/** @require-implements OtherTrait */
trait RequiresImplementingTrait {}

/** @psalm-require-implements \ArrayObject */
trait RequiresImplementingInternalClass {}

// The target is not declared. This is reported for the trait, and where the trait is used.

/** @require-extends UndeclaredClass */
trait RequiresUndeclaredClass {}

/**
 * @require-implements UndeclaredInterface
 */
trait RequiresUndeclaredInterface {}

/** @require-extends BaseClas */
trait RequiresMisspelledClass {}

class UsesUndeclared extends BaseClass
{
    use RequiresUndeclaredClass;
}

// The annotation has no class name.

/** @require-extends */
trait RequiresNothing {}

/**
 * @require-implements
 */
trait RequiresNoInterface {}

/** @require-extends int */
trait RequiresScalar {}

/** @require-implements static */
trait RequiresStatic {}

/** @psalm-require-extends object */
trait RequiresObject {}

// The issues can be suppressed in the doc comment of the trait.

/**
 * @require-extends UndeclaredClass
 * @suppress PhanUndeclaredClassReference
 */
trait SuppressedUndeclared {}

/**
 * @require-extends FooInterface
 * @suppress PhanAccessWrongInheritanceCategory
 */
trait SuppressedWrongCategory {}

/**
 * @require-implements \ArrayObject
 * @suppress PhanAccessWrongInheritanceCategoryInternal
 */
trait SuppressedWrongInternalCategory {}

/**
 * @require-implements
 * @suppress PhanUnextractableAnnotation
 */
trait SuppressedUnextractable {}

/** @require-extends self */
trait RequiresSelf {}

// `self` and `parent` in a class or interface are not resolved to a class name.

/** @require-extends self */
class RequiresSelfInClass {}

/** @require-implements SELF */
interface RequiresSelfInInterface {}

/** @require-extends parent */
class RequiresParentInClass extends BaseClass {}

/** @psalm-require-extends \self */
trait RequiresQualifiedSelf {}
