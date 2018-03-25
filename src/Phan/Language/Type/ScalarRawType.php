<?php declare(strict_types=1);
namespace Phan\Language\Type;

final class ScalarRawType extends ScalarType implements MultiType
{
    /** @override */
    const NAME = 'scalar';

    /**
     * @return array<int,ScalarType>
     */
    public function asIndividualTypeInstances() : array
    {
        if ($this->is_nullable) {
            static $nullable_types = null;
            if ($nullable_types === null) {
                $nullable_types = [BoolType::instance(true), IntType::instance(true), FloatType::instance(true), StringType::instance(true)];
            }
            return $nullable_types;
        }
        static $nonnullable_types = null;
        if ($nonnullable_types === null) {
            $nonnullable_types = [BoolType::instance(false), IntType::instance(false), FloatType::instance(false), StringType::instance(false)];
        }
        return $nonnullable_types;
    }
}
