<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Server;

use Phan\LanguageServer\FileMapping;
use Phan\LanguageServer\LanguageClient;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\FileChangeType;
use Phan\LanguageServer\Protocol\FileEvent;
use Phan\LanguageServer\Utils;

/**
 * Provides method handlers for all workspace/* methods
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Server/Workspace.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 * @phan-immutable
 */
class Workspace
{
    /**
     * @var LanguageClient represents the client of this language server.
     */
    public $client;

    /**
     * @var LanguageServer represents the LSP related functionality of the Phan Language Server.
     */
    public $server;

    /**
     * @var FileMapping this tracks the state of files opened and edited in the client.
     *
     * Any entries in this object should override the state of the files on disk.
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
     * @suppress PhanUnreferencedPublicMethod called by client via AdvancedJsonRpc
     */
    public function didChangeWatchedFiles(array $changes): void
    {
        // invalidate Phan's cache for these files if changed, added, or modified outside of the IDE
        foreach ($changes as $change) {
            $this->file_mapping->removeOverrideURI($change->uri);
        }
        // Trigger diagnostics. TODO: Is that necessary?
        foreach ($changes as $change) {
            if ($change->type === FileChangeType::DELETED) {
                $this->client->textDocument->publishDiagnostics(Utils::pathToUri(Utils::uriToPath($change->uri)), []);
            }
        }
        // TODO: more than one file
        foreach ($changes as $change) {
            // TODO: What about CREATED? Will that be emitted for renaming files?
            if ($change->type === FileChangeType::CHANGED) {
                $uri = $change->uri;
                $this->server->analyzeURIAsync($uri);
            }
        }
    }

    /**
     * no-op for now. Stop the JSON RPC2 framework from warning about this method being undefined.
     * TODO: Define this so that Phan can respond to changes in client configuration.
     * @suppress PhanUnusedPublicNoOverrideMethodParameter
     * @suppress PhanUnreferencedPublicMethod called by client via AdvancedJsonRpc
     * @suppress PhanPluginUseReturnValueNoopVoid deliberate no-op
     * @suppress PhanPluginCanUseParamType php-advanced-json-rpc Dispatcher has issues with associative arrays. https://github.com/TysonAndre/vscode-php-phan/issues/55
     *
     * @phan-param array<string,mixed> $settings @phan-unused-param NOTE: reflection-docblock does not support generic or associative arrays
     * @return void (unimplemented)
     */
    public function didChangeConfiguration($settings): void
    {
    }
}
