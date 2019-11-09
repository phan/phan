<?php declare(strict_types=1);

namespace Phan\Library;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * A PSR-3 logger for the composer Xdebug handler
 */
class StderrLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param string $level
     * @param string $message
     * @param array<string,mixed> $unused_context
     * @override
     */
    public function log($level, $message, array $unused_context = []) : void
    {
        \fprintf(\STDERR, "[%s] %s\n", $level, $message);
    }
}
