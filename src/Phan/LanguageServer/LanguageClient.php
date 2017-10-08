<?php
declare(strict_types = 1);

namespace Phan\LanguageServer;

use JsonMapper;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/LanguageClient.php
 * See ../../../LICENSE.LANGUAGE_SERVER
 */
class LanguageClient
{
    /**
     * Handles textDocument/* methods
     *
     * @var Client\TextDocument
     */
    public $textDocument;

    /**
     *
     */
    public function __construct(ProtocolReader $reader, ProtocolWriter $writer)
    {
        $handler = new ClientHandler($reader, $writer);
        $mapper = new JsonMapper;

        $this->textDocument = new Client\TextDocument($handler, $mapper);
    }
}
