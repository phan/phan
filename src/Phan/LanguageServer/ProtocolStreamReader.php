<?php
declare(strict_types = 1);

namespace Phan\LanguageServer;

use Phan\LanguageServer\Logger;
use Phan\LanguageServer\Protocol\Message;
use AdvancedJsonRpc\Message as MessageBody;
use Sabre\Event\Loop;
use Sabre\Event\Emitter;
use Exception;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/ProtocolStreamReader.php
 */
class ProtocolStreamReader extends Emitter implements ProtocolReader
{
    const PARSE_HEADERS = 1;
    const PARSE_BODY = 2;

    /** @var resource */
    private $input;
    /**
     * This is checked by ProtocolStreamReader so that it will stop reading from streams in the forked process.
     * There could be buffered bytes in stdin/over TCP, those would be processed by TCP if it were not for this check.
     * @var bool
     */
    private $is_accepting_new_requests = true;
    /** @var int */
    private $parsingMode = self::PARSE_HEADERS;
    /** @var string */
    private $buffer = '';
    /** @var string[] */
    private $headers = [];
    /** @var int */
    private $contentLength;

    /**
     * @param resource $input
     */
    public function __construct($input)
    {
        $this->input = $input;

        $this->on('close', function () {
            Loop\removeReadStream($this->input);
        });

        Loop\addReadStream($this->input, function () {
            if (feof($this->input)) {
                // If stream_select reported a status change for this stream,
                // but the stream is EOF, it means it was closed.
                $this->emit('close');
                return;
            }
            if (!$this->is_accepting_new_requests) {
                // If we fork, don't read any bytes in the input buffer from the worker process.
                $this->emit('close');
                return;
            }
            $c = '';
            while (($c = fgetc($this->input)) !== false && $c !== '') {
                $this->buffer .= $c;
                switch ($this->parsingMode) {
                    case self::PARSE_HEADERS:
                        if ($this->buffer === "\r\n") {
                            $this->parsingMode = self::PARSE_BODY;
                            $this->contentLength = (int)$this->headers['Content-Length'];
                            $this->buffer = '';
                        } elseif (substr($this->buffer, -2) === "\r\n") {
                            $parts = explode(':', $this->buffer);
                            $this->headers[$parts[0]] = trim($parts[1]);
                            $this->buffer = '';
                        }
                        break;
                    case self::PARSE_BODY:
                        if (strlen($this->buffer) === $this->contentLength) {
                            if (!$this->is_accepting_new_requests) {
                                // If we fork, don't read any bytes in the input buffer from the worker process.
                                $this->emit('close');
                                return;
                            }
                            Logger::logRequest($this->headers, $this->buffer);
                            // MessageBody::parse can throw an Error, maybe log an error?
                            try {
                                $msg = new Message(MessageBody::parse($this->buffer), $this->headers);
                            } catch (\Exception $e) {
                                $msg = null;
                            }
                            if ($msg) {
                                $this->emit('message', [$msg]);
                                if (!$this->is_accepting_new_requests) {
                                    // If we fork, don't read any bytes in the input buffer from the worker process.
                                    $this->emit('close');
                                    return;
                                }
                            }
                            $this->parsingMode = self::PARSE_HEADERS;
                            $this->headers = [];
                            $this->buffer = '';
                        }
                        break;
                }
            }
        });
    }

    /**
     * @return void
     */
    public function stopAcceptingNewRequests()
    {
        $this->is_accepting_new_requests = false;
    }
}
