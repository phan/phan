<?php

declare(strict_types=1);

namespace Phan\Debug;

use Phan\CLI;

/**
 * Utilities for debugging why Phan or a plugin is hanging or taking longer than expected.
 */
class SignalHandler
{
    /**
     * Set up the signal handlers
     * @suppress PhanAccessMethodInternal
     */
    public static function init(): void
    {
        static $did_init = false;
        if ($did_init) {
            return;
        }
        $did_init = true;
        if (!\function_exists('pcntl_async_signals')) {
            CLI::printHelpSection("WARNING: Cannot set up signal handler with --debug-without pcntl\n", false, true);
            return;
        }
        // https://wiki.php.net/rfc/async_signals allows handling OS signals without explicitly calling pcntl_dispatch everywhere.
        \pcntl_async_signals(true);
        /**
         * @param mixed $unused_signinfo
         */
        $handler = static function (int $signo, $unused_signinfo): void {
            $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
            if ($signo === \SIGINT) {
                \phan_error_handler(\E_ERROR, "Phan was interrupted with SIGINT, exiting\n", $trace[0]['file'] ?? 'unknown file', $trace[0]['line'] ?? 0);
                return;
            }
            $print_variables = $signo === \SIGUSR2;
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            \fprintf(
                \STDERR,
                "Phan was interrupted with %s, printing debug information then continuing\n",
                $signo === \SIGUSR2 ? "SIGUSR2" : "SIGUSR1"
            );
            \phan_print_backtrace($print_variables);
        };
        \pcntl_signal(\SIGINT, $handler);
        \pcntl_signal(\SIGUSR1, $handler);
        \pcntl_signal(\SIGUSR2, $handler);
    }
}
