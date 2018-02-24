<?php declare(strict_types=1);
namespace Phan\Daemon\Transport;

/**
 * Instead of sending the data over a stream,
 * this just keeps the raw array
 */
class CapturerResponder implements Responder
{
    /** @var array the data for getRequestData() */
    private $request_data;

    /** @var ?array the data sent via sendAndClose */
    private $response_data;

    public function __construct(array $data)
    {
        $this->request_data = $data;
    }

    /**
     * @return array the request data
     */
    public function getRequestData()
    {
        return $this->request_data;
    }

    /**
     * @param array<string,mixed> $data
     * @return void
     * @throws RuntimeException if called twice
     */
    public function sendResponseAndClose(array $data)
    {
        if (is_array($this->response_data)) {
            throw new \RuntimeException("Called sendResponseAndClose twice: data = " . json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        }
        $this->response_data = $data;
    }

    /**
     * @return ?array
     */
    public function getResponseData()
    {
        return $this->response_data;
    }
}
