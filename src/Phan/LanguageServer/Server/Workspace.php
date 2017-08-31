<?php
declare(strict_types = 1);

namespace Phan\LanguageServer\Server;

use Phan\LanguageServer\FileMapping;
use Phan\LanguageServer\LanguageClient;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\FileChangeType;
use Phan\LanguageServer\Protocol\FileEvent;

/**
 * Provides method handlers for all workspace/* methods
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Server/Workspace.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class Workspace
{
    /**
     * @var LanguageClient
     */
    public $client;

    /**
     * @var LanguageServer
     */
    public $server;

    /**
     * @var FileMapping
     */
    public $file_mapping;

    /**
     * @param LanguageClient    $client            LanguageClient instance used to signal updated results
     * FIXME: Rewrite to avoid static methods?
     */
    public function __construct(
        LanguageClient $client,
        LanguageServer $server,
        FileMapping $file_mapping
    ) {
        $this->client = $client;
        $this->server = $server;
        $this->file_mapping = $file_mapping;
    }

    /**
     * The watched files notification is sent from the client to the server when the client detects changes to files watched by the language client.
     *
     * @param FileEvent[] $changes
     * @return void
     */
    public function didChangeWatchedFiles(array $changes)
    {
        // invalidate Phan's cache for these files if changed, added, or modified outside of the IDE
        foreach ($changes as $change) {
            $this->file_mapping->removeOverrideURI($change->uri);
        }
        // Trigger diagnostics. TODO: Is that necessary?
        foreach ($changes as $change) {
            if ($change->type === FileChangeType::DELETED) {
                $this->client->textDocument->publishDiagnostics($change->uri, []);
            }
        }
        // TODO: more than one file
        foreach ($changes as $change) {
            if ($change->type === FileChangeType::CHANGED) {
                $uri = $change->uri;
                $this->server->analyzeURI($uri);
            }
        }
    }

    // no-op for now. Stop the JSON RPC2 framework from warning about this method being undefined.
    public function didChangeConfiguration($settings)
    {
    }
}
