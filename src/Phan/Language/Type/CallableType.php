<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\FQSEN;
use Phan\Language\Type;

final class CallableType extends NativeType
{
    /** @phan-override */
    const NAME = 'callable';

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     */
    public function isCallable() : bool
    {
        return true;
    }
}
