<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

final class StaticType extends Type
{
    const NAME = 'static';

    /**
     * @param bool $is_nullable
     * An optional parameter, which if true returns a
     * nullable instance of this native type
     *
     * @return static
     */
    public static function instance(bool $is_nullable) : Type
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if (empty($nullable_instance)) {
                $nullable_instance = static::make('\\', static::NAME, [], true, Type::FROM_TYPE);
            }

            assert($nullable_instance instanceof static);
            return $nullable_instance;
        }

        static $instance;

        if (empty($instance)) {
            $instance = static::make('\\', static::NAME, [], false, Type::FROM_TYPE);
            assert($instance instanceof static);
        }

        assert($instance instanceof static);
        return $instance;
    }

    public function isNativeType() : bool
    {
        return false;
    }

    public function isSelfType() : bool
    {
        return false;
    }

    public function isStaticType() : bool
    {
        return true;
    }

    public function isObject() : bool
    {
        return true;
    }

    public function __toString() : string
    {
        $string = $this->name;

        if ($this->getIsNullable()) {
            $string = '?' . $string;
        }

        return $string;
    }
}
