<?php

declare(strict_types=1);

namespace Phan\Daemon\Transport;

use Phan\Daemon;
use Phan\Library\StringUtil;
use TypeError;

/**
 * Sends json encoded data over a socket stream.
 */
class StreamResponder implements Responder
{
    /** @var ?resource a stream */
    private $connection;

    /** @var ?array<string,mixed> the request data */
    private $request_data;

    /** @var bool did this process already finish reading the data of the request? */
    private $did_read_request_data = false;

    /** @param resource $connection a stream */
    public function __construct($connection, bool $expect_request)
    {
        // NOTE: is_resource also checks if the resource isn't closed.
        if (!\is_resource($connection)) {
            throw new TypeError("Expected connection to be resource, saw " . \gettype($connection));
        }
        $this->connection = $connection;
        if (!$expect_request) {
            $this->did_read_request_data = true;
            $this->request_data = [];
        }
    }

    /**
     * @return ?array<string,mixed> the request data(E.g. returns null if JSON is malformed)
     */
    public function getRequestData(): ?array
    {
        if (!$this->did_read_request_data) {
            $response_connection = $this->connection;
            if (!$response_connection) {
                Daemon::debugf("Should not happen, missing a response connection");  // debugging code
                return null;
            }
            Daemon::debugf("Got a connection");  // debugging code
            $request_bytes = '';
            while (!\feof($response_connection)) {
                $request_bytes .= \fgets($response_connection);
            }
            $request = \json_decode($request_bytes, true);
            if (!\is_array($request)) {
                Daemon::debugf("Received invalid request, expected JSON: %s", StringUtil::jsonEncode($request_bytes));
                $request = null;
            }
            $this->did_read_request_data = true;
            $this->request_data = $request;
        }
        return $this->request_data;
    }

    /**
     * @param array<string,mixed> $data the response fields
     * @throws \RuntimeException if called twice
     */
    public function sendResponseAndClose(array $data): void
    {
        $connection = $this->connection;
        if (!$this->did_read_request_data) {
            throw new \RuntimeException("Called sendAndClose before calling getRequestData");
        }
        if ($connection === null) {
            throw new \RuntimeException("Called sendAndClose twice: data = " . StringUtil::jsonEncode($data));
        }
        \fwrite($connection, StringUtil::jsonEncode($data) . "\n");
        // disable further receptions and transmissions
        // Note: This is likely a giant hack,
        // and pcntl and sockets may break in the future if used together. (multiple processes owning a single resource).
        // Not sure how to do that safely.
        \stream_socket_shutdown($connection, \STREAM_SHUT_RDWR);
        \fclose($connection);
        $this->connection = null;
    }
}
