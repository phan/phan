<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

use Phan\LanguageServer\Protocol\Message;
use Sabre\Event\Loop;
use Sabre\Event\Promise;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/ProtocolStreamWriter.php
 */
class ProtocolStreamWriter implements ProtocolWriter
{
    /**
     * @var resource $output
     */
    private $output;

    /**
     * @var list<array{message:string,promise:Promise}> $messages
     */
    private $messages = [];

    /**
     * @param resource $output
     */
    public function __construct($output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function write(Message $msg): Promise
    {
        // if the message queue is currently empty, register a write handler.
        if (!$this->messages) {
            Loop\addWriteStream($this->output, function (): void {
                $this->flush();
            });
        }

        Logger::logResponse($msg->headers, (string)$msg->body);

        $promise = new Promise();
        $this->messages[] = [
            'message' => (string)$msg,
            'promise' => $promise
        ];
        return $promise;
    }

    /**
     * Writes pending messages to the output stream.
     */
    private function flush(): void
    {
        $keepWriting = true;
        while ($keepWriting) {
            $message = $this->messages[0]['message'];
            $promise = $this->messages[0]['promise'];

            $bytesWritten = @\fwrite($this->output, $message);

            if ($bytesWritten > 0) {
                $message = (string)\substr($message, $bytesWritten);
            }

            // Determine if this message was completely sent
            // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
            if (\strlen($message) === 0) {
                \array_shift($this->messages);

                // This was the last message in the queue, remove the write handler.
                if (\count($this->messages) === 0) {
                    Loop\removeWriteStream($this->output);
                    $keepWriting = false;
                }

                $promise->fulfill();
            } else {
                $this->messages[0]['message'] = $message;
                $keepWriting = false;
            }
        }
    }
}
