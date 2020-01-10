<?php

declare(strict_types=1);

namespace Phan\Daemon\Transport;

use Phan\Library\StringUtil;

/**
 * Instead of sending the data over a stream,
 * this just keeps the raw array
 */
class CapturerResponder implements Responder
{
    /** @var array<string,mixed> the data for getRequestData() */
    private $request_data;

    /** @var ?array<string,mixed> the data sent via sendAndClose */
    private $response_data;

    /** @param array<string,mixed> $data the data for getRequestData() */
    public function __construct(array $data)
    {
        $this->request_data = $data;
    }

    /**
     * @return array<string,mixed> the request data
     */
    public function getRequestData(): array
    {
        return $this->request_data;
    }

    /**
     * @param array<string,mixed> $data
     * @throws \RuntimeException if called twice
     */
    public function sendResponseAndClose(array $data): void
    {
        if (\is_array($this->response_data)) {
            throw new \RuntimeException("Called sendResponseAndClose twice: data = " . StringUtil::jsonEncode($data));
        }
        $this->response_data = $data;
    }

    /**
     * @return ?array<string,mixed> the raw response data that the analysis would have sent back serialized if this was actually a fork.
     */
    public function getResponseData(): ?array
    {
        return $this->response_data;
    }
}
