<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Type;

/**
 * Represents the PHPDoc type `self` or `static`.
 * This is converted to a real class when necessary.
 * @see self::withStaticResolvedInContext()
 * @phan-pure
 */
abstract class StaticOrSelfType extends Type
{
    /**
     * @unused-param $code_base
     * @override
     */
    public function hasStaticOrSelfTypesRecursive(CodeBase $code_base): bool
    {
        return true;
    }
}
