<?php

declare(strict_types=1);

namespace Phan;

use AssertionError;
use Closure;
use Exception;
use InvalidArgumentException;
use Phan\Daemon\ExitException;
use Phan\Daemon\Request;
use Phan\Daemon\Transport\StreamResponder;
use RuntimeException;

/**
 * A simple analyzing daemon that can be used by IDEs. (see `phan_client`)
 * Accepts requests (Currently only JSON blobs) over a Unix socket or TCP socket.
 *
 * @see \Phan\LanguageServer\LanguageServer for an implementation of the Language Server Protocol
 */
class Daemon
{
    /**
     * This creates an analyzing daemon, to be used by IDEs.
     * Format:
     *
     * - Read over TCP socket, e.g. with JSON
     * - Respond over TCP socket, e.g. with JSON
     *
     * @param CodeBase $code_base (Must have undo tracker enabled)
     *
     * @param Closure $file_path_lister
     * Returns string[] - A list of files to scan. This may be different from the previous contents.
     *
     * @return Request|null - A writable request, which has been fully read from.
     * Callers should close after they are finished writing.
     *
     * @throws Exception if analysis fails unexpectedly
     */
    public static function run(CodeBase $code_base, Closure $file_path_lister): ?Request
    {
        if (Config::getValue('language_server_use_pcntl_fallback')) {
            self::runWithoutPcntl($code_base, $file_path_lister);
            // Not reachable
            exit(0);
        }
        if (!$code_base->isUndoTrackingEnabled()) {
            throw new AssertionError("Expected undo tracking to be enabled when starting daemon mode");
        }

        // example requests over TCP
        // Assumes that clients send and close the their requests quickly, then wait for a response.

        // {"method":"analyze","files":["/path/to/file1.php","/path/to/file2.php"]}

        $socket_server = self::createDaemonStreamSocketServer();
        // TODO: Limit the maximum number of active processes to a small number(4?)
        try {
            $got_signal = false;

            if (\function_exists('pcntl_signal')) {
                \pcntl_signal(
                    \SIGCHLD,
                    /** @param ?(int|array) $status */
                    static function (int $signo, $status = null, ?int $pid = null) use (&$got_signal): void {
                        $got_signal = true;
                        Request::childSignalHandler($signo, $status, $pid);
                    }
                );
            }
            while (true) {
                $got_signal = false;  // reset this.
                // We get an error from stream_socket_accept. After the RuntimeException is thrown, pcntl_signal is called.
                /**
                 * @param int $severity
                 * @param string $message
                 * @param string $file
                 * @param int $line
                 * @return bool
                 */
                $previous_error_handler = \set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$previous_error_handler): bool {
                    self::debugf("In new error handler '$message'");
                    if (!\preg_match('/stream_socket_accept/i', $message)) {
                        return $previous_error_handler($severity, $message, $file, $line);
                    }
                    throw new RuntimeException("Got signal");
                });

                $conn = false;
                try {
                    $conn = \stream_socket_accept($socket_server, -1);
                } catch (RuntimeException $_) {
                    self::debugf("Got signal");
                    \pcntl_signal_dispatch();
                    self::debugf("done processing signals");
                    if ($got_signal) {
                        continue;  // Ignore notices from stream_socket_accept if it's due to being interrupted by a child process terminating.
                    }
                } finally {
                    \restore_error_handler();
                }

                if (!$conn) {
                    // If we didn't get a connection, and it wasn't due to a signal from a child process, then stop the daemon.
                    break;
                }
                $request = Request::accept(
                    $code_base,
                    $file_path_lister,
                    new StreamResponder($conn, true),
                    true
                );
                if ($request instanceof Request) {
                    return $request;  // We forked off a worker process successfully, and this is the worker process
                }
            }
            \error_log("Stopped accepting connections");
        } finally {
            \restore_error_handler();
        }
        return null;
    }

    /**
     * @return void - A writable request, which has been fully read from.
     * Callers should close after they are finished writing.
     *
     * @throws Exception if analysis failed in an unexpected way
     */
    private static function runWithoutPcntl(CodeBase $code_base, Closure $file_path_lister): void
    {
        // This is a single threaded server, it only analyzes one TCP request at a time
        $socket_server = self::createDaemonStreamSocketServer();
        try {
            while (true) {
                // We get an error from stream_socket_accept. After the RuntimeException is thrown, pcntl_signal is called.
                $previous_error_handler = \set_error_handler(
                    static function (int $severity, string $message, string $file, int $line) use (&$previous_error_handler): bool {
                        self::debugf("In new error handler '$message'");
                        if (!\preg_match('/stream_socket_accept/i', $message)) {
                            return $previous_error_handler($severity, $message, $file, $line);
                        }
                        throw new RuntimeException("Got signal");
                    }
                );

                $conn = false;
                try {
                    $conn = \stream_socket_accept($socket_server, -1);
                } catch (RuntimeException $_) {
                    self::debugf("Got signal");
                    \pcntl_signal_dispatch();
                    self::debugf("done processing signals");
                } finally {
                    \restore_error_handler();
                }

                if (!$conn) {
                    // If we didn't get a connection, and it wasn't due to a signal from a child process, then stop the daemon.
                    break;
                }
                // We **are** the only process. Imitate the worker process
                $request = Request::accept(
                    $code_base,
                    $file_path_lister,
                    new StreamResponder($conn, true),
                    false  // This is not a fork, do not call exit($status)
                );
                if ($request instanceof Request) {
                    self::debugf("Calling analyzeDaemonRequestOnMainThread\n");
                    // This did not fork, and will not fork (Unless --processes N was used)
                    self::analyzeDaemonRequestOnMainThread($code_base, $request);
                    // Force garbage collection in case it didn't respond
                    $request = null;
                    self::debugf("Finished call to analyzeDaemonRequestOnMainThread\n");
                    // We did not terminate, we keep accepting
                }
            }
            \error_log("Stopped accepting connections");
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * @throws Exception if analysis throws
     */
    private static function analyzeDaemonRequestOnMainThread(CodeBase $code_base, Request $request): void
    {
        // Stop tracking undo operations, now that the parse phase is done.
        // TODO: Save and reset $code_base in place
        $analyze_file_path_list = $request->filterFilesToAnalyze($code_base->getParsedFilePathList());
        Phan::setPrinter($request->getPrinter());
        if (\count($analyze_file_path_list) === 0) {
            // Nothing to do, don't start analysis
            $request->respondWithNoFilesToAnalyze();  // respond and exit.
            return;
        }
        $restore_point = $code_base->createRestorePoint();
        $code_base->disableUndoTracking();

        $temporary_file_mapping = $request->getTemporaryFileMapping();

        try {
            Phan::finishAnalyzingRemainingStatements($code_base, $request, $analyze_file_path_list, $temporary_file_mapping);
        } catch (ExitException $_) {
            // This is normal and expected, do nothing
        } finally {
            $code_base->restoreFromRestorePoint($restore_point);
        }
    }

    /**
     * @return resource (resource is not a reserved keyword)
     * @throws InvalidArgumentException if the config does not specify a method. (should not happen)
     */
    private static function createDaemonStreamSocketServer()
    {
        if (Config::getValue('daemonize_socket')) {
            $listen_url = 'unix://' . Config::getValue('daemonize_socket');
        } elseif (Config::getValue('daemonize_tcp')) {
            $listen_url = \sprintf('tcp://%s:%d', Config::getValue('daemonize_tcp_host'), Config::getValue('daemonize_tcp_port'));
        } else {
            throw new InvalidArgumentException("Should not happen, no port/socket for daemon to listen on.");
        }
        // @phan-suppress-next-line PhanPluginRemoveDebugCall this is deliberate output.
        \printf(
            "Listening for Phan analysis requests at %s\nAwaiting analysis requests for directory %s\n",
            $listen_url,
            \var_representation(Config::getProjectRootDirectory())
        );
        $socket_server = \stream_socket_server($listen_url, $errno, $errstr);
        if (!$socket_server) {
            \error_log("Failed to create Unix socket server $listen_url: $errstr ($errno)\n");
            exit(1);
        }
        return $socket_server;
    }

    /**
     * Debug (non-error) statement related to the daemon.
     * Set PHAN_DAEMON_ENABLE_DEBUG=1 when debugging.
     *
     * @param string $format - printf style format string
     * @param mixed ...$args - printf args
     * @no-named-arguments
     * @suppress PhanPluginPrintfVariableFormatString
     */
    public static function debugf(string $format, ...$args): void
    {
        if (\getenv('PHAN_DAEMON_ENABLE_DEBUG')) {
            if (\count($args) > 0) {
                $message = \sprintf($format, ...$args);
            } else {
                $message = $format;
            }
            // @phan-suppress-next-line PhanPluginRemoveDebugCall printing to stderr is deliberate
            \fwrite(\STDERR, $message . "\n");
        }
    }
}
