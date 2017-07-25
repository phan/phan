<?php declare(strict_types=1);
namespace Phan\Language\Type;

final class VoidType extends NativeType
{
    /** @phan-override */
    const NAME = 'void';
}
