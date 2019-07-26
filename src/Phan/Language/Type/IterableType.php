<?php declare(strict_types=1);

namespace Phan\Language\Type;

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
}
// Trigger autoloader for subclass before make() can get called.
\class_exists(GenericIterableType::class);
\class_exists(ArrayType::class);
