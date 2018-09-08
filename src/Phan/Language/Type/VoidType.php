<?php declare(strict_types=1);
namespace Phan\Language\Type;

/**
 * Represents the return type `void`
 */
final class VoidType extends NativeType
{
    /** @phan-override */
    const NAME = 'void';
}
