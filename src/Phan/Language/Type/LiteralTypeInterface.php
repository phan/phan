<?php

declare(strict_types=1);

namespace Phan\Language\Type;

/**
 * Empty interface used by quick checks if a Type is a specific literal int/string.
 * @method ?int|?string|?float|?bool getValue()
 * @phan-pure
 */
interface LiteralTypeInterface
{
    // has getValue(), instance_for_value, etc.
    // Document with @method?
}
