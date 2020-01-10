<?php

declare(strict_types=1);

namespace Phan\Daemon;

/**
 * An exception thrown to indicate that the caller should exit() with the given error code.
 *
 * This is thrown instead of directly calling exit()
 * so that code that exits can be unit tested,
 * or so that the forked processes of Phan language servers
 * can clean up and finish reporting analysis results.
 */
class ExitException extends \Exception
{
}
