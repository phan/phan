<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of `iterable`
 * @see GenericIterableType for the representation of `iterable<KeyType,ValueType>`
 */
class IterableType extends NativeType
{
    /** @phan-override */
    const NAME = 'iterable';

    public function isIterable() : bool
    {
        return true;
    }

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $unused_context, Type $other) : bool
    {
        // TODO: Check if $other is final and non-iterable
        return $other instanceof IterableType || $other instanceof CallableDeclarationType || $other->isPossiblyObject();
    }


    public function asIterable(CodeBase $_) : ?Type
    {
        return $this->withIsNullable(false);
    }

    public function isPrintableScalar() : bool
    {
        return false;
    }

    public function isValidBitwiseOperand() : bool
    {
        return false;
    }

    public function isPossiblyObject() : bool
    {
        return true;  // can be Traversable, which is an object
    }

    public function asObjectType() : ?Type
    {
        return Type::traversableInstance();
    }

    public function asArrayType() : ?Type
    {
        return ArrayType::instance(false);
    }

    public function isAlwaysTruthy() : bool
    {
        return false;
    }

    public function isPossiblyTruthy() : bool
    {
        return true;
    }

    public function isPossiblyFalsey() : bool
    {
        return true;
    }

    public function isAlwaysFalsey() : bool
    {
        return false;
    }
}
// Trigger autoloader for subclass before make() can get called.
\class_exists(GenericIterableType::class);
\class_exists(ArrayType::class);
