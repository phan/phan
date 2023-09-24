<?php

namespace Phan\Language\Type;

/**
 * The base class for various array-key types IntType, StringType
 * @phan-pure
 */
class ArrayKeyType extends ScalarType implements MultiType
{
    use NativeTypeTrait;

    /** @override */
    public const NAME = 'array-key';

    /**
     * @return array{0:IntType,1:StringType}
     */
    public function asIndividualTypeInstances(): array
    {
        if ($this->is_nullable) {
            static $nullable_types = null;
            if ($nullable_types === null) {
                $nullable_types = [IntType::instance(true), StringType::instance(true)];
            }
            return $nullable_types;
        }
        static $nonnullable_types = null;
        if ($nonnullable_types === null) {
            $nonnullable_types = [IntType::instance(false), StringType::instance(false)];
        }
        return $nonnullable_types;
    }
}
