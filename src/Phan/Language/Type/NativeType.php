<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;

abstract class NativeType extends Type
{
    const NAME = '';

    /**
     * @param bool $is_nullable
     * If true, returns a nullable instance of this native type
     *
     * @return static
     */
    public static function instance(bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if (empty($nullable_instance)) {
                $nullable_instance = static::make('\\', static::NAME, [], true, false);
            }
            assert($nullable_instance instanceof static);

            return $nullable_instance;
        }

        static $instance = null;

        if (empty($instance)) {
            $instance = static::make('\\', static::NAME, [], false, false);
        }

        assert($instance instanceof static);
        return $instance;
    }

    public function isNativeType() : bool
    {
        return true;
    }

    public function isSelfType() : bool
    {
        return false;
    }

    public function isArrayAccess() : bool
    {
        return false;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if (!($type instanceof NativeType)
            || $this instanceof GenericArrayType
            || $type instanceof GenericArrayType
        ) {
            return parent::canCastToNonNullableType($type);
        }

        // Cast this to a native type
        assert($type instanceof NativeType);

        // A nullable type cannot cast to a non-nullable type
        if ($this->getIsNullable() && !$type->getIsNullable()) {
            return false;
        }

        // A matrix of allowable type conversions between
        // the various native types.
        static $matrix = [
            ArrayType::NAME => [
                ArrayType::NAME => true,
                IterableType::NAME => true,
                BoolType::NAME => false,
                CallableType::NAME => true,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            IterableType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => true,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            BoolType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => true,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            CallableType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => true,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            FloatType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => true,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            IntType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => true,
                IntType::NAME => true,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            MixedType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            NullType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => true,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            ObjectType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => true,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            ResourceType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => true,
                StringType::NAME => false,
                VoidType::NAME => false,
            ],
            StringType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => true,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => true,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => true,
                VoidType::NAME => false,
            ],
            VoidType::NAME => [
                ArrayType::NAME => false,
                IterableType::NAME => false,
                BoolType::NAME => false,
                CallableType::NAME => false,
                FloatType::NAME => false,
                IntType::NAME => false,
                MixedType::NAME => false,
                NullType::NAME => false,
                ObjectType::NAME => false,
                ResourceType::NAME => false,
                StringType::NAME => false,
                VoidType::NAME => true,
            ],
        ];

        return $matrix[$this->getName()][$type->getName()]
            ?? parent::canCastToNonNullableType($type);
    }

    public function __toString() : string
    {
        // Native types can just use their
        // non-fully-qualified names
        $string = $this->name;

        if ($this->getIsNullable()) {
            $string = '?' . $string;
        }

        return $string;
    }

    public function asFQSENString() : string
    {
        return $this->name;
    }
}
