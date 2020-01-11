<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

use AdvancedJsonRpc\Message as MessageBody;
use Exception;
use Phan\LanguageServer\Protocol\Message;
use Sabre\Event\Emitter;
use Sabre\Event\Loop;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/ProtocolStreamReader.php
 */
class ProtocolStreamReader extends Emitter implements ProtocolReader
{
    public const PARSE_HEADERS = 1;
    public const PARSE_BODY = 2;

    /** @var resource the input stream resource for data from the client. */
    private $input;

    /**
     * This is checked by ProtocolStreamReader so that it will stop reading from streams in the forked process.
     * There could be buffered bytes in stdin/over TCP, those would be processed by TCP if it were not for this check.
     * @var bool
     */
    private $is_accepting_new_requests = true;
    /** @var int (self::PARSE_*) the state of the parsing state machine */
    private $parsing_mode = self::PARSE_HEADERS;
    /** @var string the intermediate state of the buffer */
    private $buffer = '';
    /** @var string[] the headers that were parsed during the PARSE_HEADERS phase */
    private $headers = [];
    /** @var int the content-length that we are expecting */
    private $content_length;
    /** @var bool was a close notification sent to the listeners already? */
    private $did_emit_close = false;

    /**
     * @param resource $input
     */
    public function __construct($input)
    {
        $this->input = $input;

        $this->on('close', function (): void {
            Loop\removeReadStream($this->input);
        });

        Loop\addReadStream($this->input, function (): void {
            if (\feof($this->input)) {
                // If stream_select reported a status change for this stream,
                // but the stream is EOF, it means it was closed.
                $this->emitClose();
                return;
            }
            if (!$this->is_accepting_new_requests) {
                // If we fork, don't read any bytes in the input buffer from the worker process.
                $this->emitClose();
                return;
            }
            $emitted_messages = $this->readMessages();
            if ($emitted_messages > 0) {
                $this->emit('readMessageGroup');
            }
        });
    }

    private function readMessages(): int
    {
        $emitted_messages = 0;
        while (($c = \fgetc($this->input)) !== false && $c !== '') {
            $this->buffer .= $c;
            switch ($this->parsing_mode) {
                case self::PARSE_HEADERS:
                    if ($this->buffer === "\r\n") {
                        $this->parsing_mode = self::PARSE_BODY;
                        $this->content_length = (int)$this->headers['Content-Length'];
                        $this->buffer = '';
                    } elseif (\substr($this->buffer, -2) === "\r\n") {
                        $parts = \explode(':', $this->buffer);
                        $this->headers[$parts[0]] = \trim($parts[1]);
                        $this->buffer = '';
                    }
                    break;
                case self::PARSE_BODY:
                    if (\strlen($this->buffer) < $this->content_length) {
                        // We know the number of remaining bytes to read - try to read them all at once.
                        $buf = \fread($this->input, $this->content_length - \strlen($this->buffer));
                        if (\is_string($buf) && \strlen($buf) > 0) {
                            $this->buffer .= $buf;
                        }
                    }
                    if (\strlen($this->buffer) === $this->content_length) {
                        if (!$this->is_accepting_new_requests) {
                            // If we fork, don't read any bytes in the input buffer from the worker process.
                            $this->emitClose();
                            return $emitted_messages;
                        }
                        Logger::logRequest($this->headers, $this->buffer);
                        // MessageBody::parse can throw an Error, maybe log an error?
                        try {
                            $msg = new Message(MessageBody::parse($this->buffer), $this->headers);
                        } catch (Exception $_) {
                            $msg = null;
                        }
                        if ($msg) {
                            $emitted_messages++;
                            $this->emit('message', [$msg]);
                            if (!$this->is_accepting_new_requests) {
                                // If we fork, don't read any bytes in the input buffer from the worker process.
                                $this->emitClose();
                                return $emitted_messages;
                            }
                        }
                        $this->parsing_mode = self::PARSE_HEADERS;
                        $this->headers = [];
                        $this->buffer = '';
                    }
                    break;
            }
        }
        return $emitted_messages;
    }

    public function stopAcceptingNewRequests(): void
    {
        $this->is_accepting_new_requests = false;
    }

    private function emitClose(): void
    {
        if ($this->did_emit_close) {
            return;
        }
        $this->did_emit_close = true;
        $this->emit('close');
    }
}
