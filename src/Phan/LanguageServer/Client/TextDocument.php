<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Client;

use Phan\LanguageServer\ClientHandler;
use Phan\LanguageServer\Protocol\Diagnostic;
use Sabre\Event\Promise;

/**
 * Provides method handlers for all textDocument/* methods
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/TextDocument.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class TextDocument
{
    /**
     * Used to send `textDocument/*` notifications and requests to the language server client of the Phan Language Server.
     *
     * @var ClientHandler
     */
    private $handler;

    public function __construct(ClientHandler $handler)
    {
        $this->handler = $handler;
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
