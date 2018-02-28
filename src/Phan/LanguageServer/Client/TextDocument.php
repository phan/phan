<?php
declare(strict_types = 1);

namespace Phan\LanguageServer\Client;

use Phan\LanguageServer\ClientHandler;
use Phan\LanguageServer\Protocol\Diagnostic;
use Sabre\Event\Promise;
use JsonMapper;

/**
 * Provides method handlers for all textDocument/* methods
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/TextDocument.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class TextDocument
{
    /**
     * @var ClientHandler
     */
    private $handler;

    /**
     * @var JsonMapper
     */
    private $mapper;

    public function __construct(ClientHandler $handler, JsonMapper $mapper)
    {
        $this->handler = $handler;
        $this->mapper = $mapper;
    }

    /**
     * Diagnostics notification are sent from the server to the client to signal results of validation runs.
     *
     * @param string $uri
     * @param Diagnostic[] $diagnostics
     * @return Promise <void>
     */
    public function publishDiagnostics(string $uri, array $diagnostics): Promise
    {
        return $this->handler->notify('textDocument/publishDiagnostics', [
            'uri' => $uri,
            'diagnostics' => $diagnostics
        ]);
    }
}
