<?php declare(strict_types=1);

namespace Phan\ForkPool;

use Closure;
use TypeError;

/**
 * This reads messages from a forked worker.
 * This reuses code from ProtocolStreamReader.
 *
 * This is designed to accept notifications in a similar format to JSON-RPC (arbitrarily).
 * @internal
 */
class Reader
{
    const PARSE_HEADERS = 1;
    const PARSE_BODY = 2;

    /** @var resource */
    private $input;

    /** @var string the bytes read from the worker */
    private $buffer = '';

    /** @var 1|2 either PARSE_HEADERS or PARSE_BODY */
    private $parsing_mode = self::PARSE_HEADERS;

    /** @var int the length of the body of the next notification. */
    private $content_length;

    /** @var string the type of the next notification. */
    private $notification_type;

    /** @var array<string,string> the JSON-RPC headers. Currently just Content-Length. */
    private $headers;

    /** @var Closure(string,string):void $notification_handler */
    private $notification_handler;

    /** @var array<string,int> $read_messages the count of read messages of each type */
    private $read_messages = [];

    /** @var bool was the end of the input stream reached */
    private $eof = false;

    /**
     * @param resource $input
     * @param Closure(string,string):void $notification_handler
     */
    public function __construct($input, Closure $notification_handler)
    {
        if (!\is_resource($input)) {
            throw new TypeError('Expected resource for $input, got ' . \gettype($input));
        }
        $this->input = $input;
        $this->notification_handler = $notification_handler;
    }

    /**
     * Read serialized messages from the analysis workers
     */
    public function readMessages() : void
    {
        if ($this->eof) {
            return;
        }
        while (($c = \fgetc($this->input)) !== false && $c !== '') {
            $this->buffer .= $c;
            switch ($this->parsing_mode) {
                case self::PARSE_HEADERS:
                    if ($this->buffer === "\r\n") {
                        $this->parsing_mode = self::PARSE_BODY;
                        $this->content_length = (int)$this->headers['Content-Length'];
                        $this->notification_type = $this->headers['Notification-Type'] ?? 'unknown';
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
                        $this->read_messages[$this->notification_type] = ($this->read_messages[$this->notification_type] ?? 0) + 1;
                        ($this->notification_handler)($this->notification_type, $this->buffer);
                        $this->parsing_mode = self::PARSE_HEADERS;
                        $this->headers = [];
                        $this->buffer = '';
                    }
                    break;
            }
        }
        $this->eof = \feof($this->input);
    }

    /**
     * Returns an error message for errors caused by an analysis worker exiting abnormally or sending invalid data.
     * During normal operation, should return null.
     */
    public function computeErrorsAfterRead() : ?string
    {
        $error = "";
        if ($this->buffer) {
            $error .= \sprintf("Saw non-empty buffer of length %d\n", \strlen($this->buffer));
        }
        if ($this->parsing_mode !== self::PARSE_HEADERS) {
            $error .= "Expected to be finished parsing the last message body\n";
        }
        if (!$this->eof) {
            $error .= "Expected to reach eof\n";
        }
        if (!isset($this->read_messages[Writer::TYPE_ISSUE_LIST])) {
            $error .= "Expected to have received a list of 0 or more issues (as the last notification)\n";
        }
        return $error ?: null;
    }
}
