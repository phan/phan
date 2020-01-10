<?php

declare(strict_types=1);

namespace Phan\Exception;

use RuntimeException;

/**
 * Thrown to indicate that recursion exceeded the limits of what Phan supports
 */
class RecursionDepthException extends RuntimeException
{
}
