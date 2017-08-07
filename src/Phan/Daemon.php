<?php declare(strict_types=1);
namespace Phan;

use Phan\Daemon\Request;
use Phan\Library\FileCache;

/**
 * an analyzing daemon, to be used by IDEs.
 * Accepts requests (Currently only JSON blobs) over a unix socket or TCP sockets.
 * TODO: HTTP support, or open language protocol support
 */
class Daemon {
    /**
     * This creates an analyzing daemon, to be used by IDEs.
     * Format:
     *
     * - Read over TCP socket, e.g. with JSON
     * - Respond over TCP socket, e.g. with JSON
     *
     * @param CodeBase $code_base (Must have undo tracker enabled)
     *
     * @param \Closure $file_path_lister
     * Returns string[] - A list of files to scan. This may be different from the previous contents.
     *
     * @return Request|null - A writeable request, which has been fully read from.
     * Callers should close after they are finished writing.
     *
     * @suppress PhanUndeclaredConstant (pcntl unavailable on Windows)
     */
    public static function run(CodeBase $code_base, \Closure $file_path_lister) {
        \assert($code_base->isUndoTrackingEnabled());

        $receivedSignal = false;
        // example requests over TCP
        // Assumes that clients send and close the their requests quickly, then wait for a response.

        // {"method":"analyze","files":["/path/to/file1.php","/path/to/file2.php"]}

        $socket_server = self::createDaemonStreamSocketServer();
        // TODO: Limit the maximum number of active processes to a small number(4?)
        // TODO: accept SIGCHLD when child terminates, somehow?
        try {
            $gotSignal = false;
            pcntl_signal(SIGCHLD, function(...$args) use(&$gotSignal) {
                $gotSignal = true;
                Request::childSignalHandler(...$args);
            });
            while (true) {
                $gotSignal = false;  // reset this.
                // We get an error from stream_socket_accept. After the RuntimeException is thrown, pcntl_signal is called.
				$previousErrorHandler = set_error_handler(function ($severity, $message, $file, $line) use (&$previousErrorHandler) {
                    self::debugf("In new error handler '$message'");
					if (!preg_match('/stream_socket_accept/i', $message)) {
						return $previousErrorHandler($severity, $message, $file, $line);
					}
                    throw new \RuntimeException("Got signal");
				});

                $conn = false;
                try {
                    $conn = stream_socket_accept($socket_server, -1);
                } catch(\RuntimeException $e) {
                    self::debugf("Got signal");
                    pcntl_signal_dispatch();
                    self::debugf("done processing signals");
                    if ($gotSignal) {
                        continue;  // Ignore notices from stream_socket_accept if it's due to being interrupted by a child process terminating.
                    }
                } finally {
                    restore_error_handler();
                }

                if (!\is_resource($conn)) {
                    // If we didn't get a connection, and it wasn't due to a signal from a child process, then stop the daemon.
                    break;
                }
                $request = Request::accept($code_base, $file_path_lister, $conn);
                if ($request instanceof Request) {
                    return $request;  // We forked off a worker process successfully, and this is the worker process
                }
            }
            error_log("Stopped accepting connections");
        } finally {
            restore_error_handler();
        }
        return null;
    }

    /**
     * @return resource (resource is not a reserved keyword)
     */
    private static function createDaemonStreamSocketServer() {
        $listen_url = null;
        if (Config::getValue('daemonize_socket')) {
            $listen_url = 'unix://' . Config::getValue('daemonize_socket');
        } else if (Config::getValue('daemonize_tcp_port')) {
            $listen_url = sprintf('tcp://127.0.0.1:%d', Config::getValue('daemonize_tcp_port'));
        } else {
            throw new \InvalidArgumentException("Should not happen, no port/socket for daemon to listen on.");
        }
        echo "Listening for Phan analysis requests at $listen_url\n";
        $socket_server = stream_socket_server($listen_url, $errno, $errstr);
        if (!$socket_server) {
            error_log("Failed to create unix socket server $listen_url: $errstr ($errno)\n");
            exit(1);
        }
        return $socket_server;
    }

    /**
     * Debug (non-error) statement related to the daemon.
     * Uncomment this when debugging issues (E.g. changes not being picked up)
     *
     * @param string $format - printf style format string
     * @param mixed ...$args - printf args
     */
    public static function debugf(string $format, ...$args) {
        /*
        if (count($args) > 0) {
            $message = sprintf($format, ...$args);
        } else {
            $message = $format;
        }
        error_log($message);
        */
    }

}
