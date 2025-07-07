<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of the type for `callable-array`.
 *
 * NOTE: A CallableArrayType is not technically a list type because [1 => $methodName, 0 => $classOrObject] is also callable.
 * @phan-pure
 */
class CallableArrayType extends ArrayType implements GenericArrayInterface
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'callable-array';

    public function isAlwaysTruthy(): bool
    {
        return !$this->is_nullable;
    }

    public function isPossiblyTruthy(): bool
    {
        return true;
    }

    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable;
    }

    /**
     * @unused-param $code_base
     * @return UnionType int|string for arrays
     * @override
     */
    public function iterableKeyUnionType(CodeBase $code_base): UnionType
    {
        // Reduce false positive partial type mismatch errors
        return IntType::instance(false)->asRealUnionType();
    }

    /**
     * @unused-param $code_base
     * @override
     */
    public function iterableValueUnionType(CodeBase $code_base): UnionType
    {
        return $this->genericArrayElementUnionType();
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        if ($other->isDefiniteNonCallableType($code_base)) {
            return false;
        }
        if ($other instanceof IterableType) {
            return true;
        }
        // TODO: More specific.
        // e.g. can't cast to certain array shapes or arrays with string keys.
        return $other instanceof CallableType
            || $other instanceof CallableDeclarationType
            || parent::canCastToDeclaredType($code_base, $context, $other);
    }

    public function isDefinitelyNonEmptyArray(): bool {
        return true;
    }

    public function getKeyType(): int {
        return GenericArrayType::KEY_INT;
    }

    public function genericArrayElementUnionType(): UnionType {
        return UnionType::fromFullyQualifiedRealString('string|object');
    }

    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof NonEmptyGenericArrayType) {
            return $type->getKeyType() !== NonEmptyGenericArrayType::KEY_STRING &&
                $this->genericArrayElementUnionType()->isStrictSubtypeOf($code_base, $type->genericArrayElementUnionType());
        }
        return parent::isSubtypeOfNonNullableType($type, $code_base);
    }
}
