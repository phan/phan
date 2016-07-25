<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

final class StaticType extends Type
{
    const NAME = 'static';

    public static function instance() : Type
    {
        static $instance;

        if (empty($instance)) {
            $instance = static::make('\\', static::NAME, []);
        }

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

    public function __toString() : string
    {
        // Native types can just use their
        // non-fully-qualified names
        return $this->name;
    }
}
