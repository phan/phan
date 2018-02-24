<?php declare(strict_types=1);
namespace Phan\Daemon\Transport;

/**
 * This is an interface abstracting the transport which the worker process uses to send a response.
 *
 * StreamResponder is used for pcntl when this process is the fork
 * CapturerResponder is used when a single process is run
 */
interface Responder
{

    /**
     * @return ?array the request data(E.g. returns null if JSON is malformed)
     */
    function getRequestData();

    /**
     * This must be called exactly once
     * @return void
     */
    function sendResponseAndClose(array $data);
}
