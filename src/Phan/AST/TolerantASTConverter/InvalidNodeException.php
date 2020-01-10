<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Exception;

/**
 * An exception thrown when TolerantASTConverter is processing something that would become an invalid Node.
 *
 * This is caught within TolerantASTConverter, the way it is handled depends on configuration settings.
 * @internal
 */
final class InvalidNodeException extends Exception
{
}
