<?php declare(strict_types = 1);

namespace Phan\LanguageServer\Server;

use Phan\LanguageServer\FileMapping;
use Phan\LanguageServer\LanguageClient;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Logger;
use Phan\LanguageServer\Protocol\TextDocumentContentChangeEvent;
use Phan\LanguageServer\Protocol\TextDocumentIdentifier;
use Phan\LanguageServer\Protocol\TextDocumentItem;
use Phan\LanguageServer\Protocol\VersionedTextDocumentIdentifier;
use Phan\LanguageServer\Utils;

/**
 * Provides method handlers for all textDocument/* methods
 * Source: https://github.com/felixfbecker/php-language-server/blob/master/src/Server/TextDocument.php
 */
class TextDocument
{
    /**
     * The language client object to call methods on the client
     *
     * @var LanguageClient
     */
    protected $client;

    /**
     * The language client object to call methods on the server
     *
     * @var LanguageServer
     */
    protected $server;

    /**
     * Maps paths of files on disk to overrides.
     * @var FileMapping
     */
    protected $file_mapping;

    /**
     * @param LanguageClient $client
     * @param FileMapping $file_mapping
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
     * The document symbol request is sent from the client to the server to list all symbols found in a given text
     * document.
     * FIXME: reintroduce when support is added back
     *
     * @param TextDocumentIdentifier $textDocument
     * @return Promise <SymbolInformation[]>
     */
    /*
    public function documentSymbol(TextDocumentIdentifier $textDocument): Promise
    {
        return $this->documentLoader->getOrLoad($textDocument->uri)->then(function (PhpDocument $document) {
            $symbols = [];
            foreach ($document->getDefinitions() as $fqn => $definition) {
                $symbols[] = $definition->symbolInformation;
            }
            return $symbols;
        });
    }
     */

    /**
     * The document open notification is sent from the client to the server to signal newly opened text documents. The
     * document's truth is now managed by the client and the server must not try to read the document's truth using the
     * document's uri.
     *
     * @param TextDocumentItem $textDocument The document that was opened.
     * @return void
     * @suppress PhanUnreferencedPublicMethod called by client via AdvancedJsonRpc
     */
    public function didOpen(TextDocumentItem $textDocument)
    {
        $this->file_mapping->addOverrideURI($textDocument->uri, $textDocument->text);
        Logger::logInfo("Called didOpen, uri={$textDocument->uri}");
        $this->server->analyzeURIAsync($textDocument->uri);

        //$document = $this->documentLoader->open($textDocument->uri, $textDocument->text);
        // TODO: make this trigger re-analysis
        // TODO: Check based on parse and analyze directories and Phan supported file extensions if this file affects Phan's analysis.
        // TODO:   Add functions to quickly check if a relative/absolute path is within the parse or analysis list of a project
        // TODO:   Maybe allow reloading .phan/config, at least the files and directories to parse/analyze
    }

    /**
     * The document save notification is sent from the client to the server when the document was saved in the client.
     * TODO: Should this use willSave instead
     * TODO: Why is this not triggering on Ctrl+S
     *
     * @param VersionedTextDocumentIdentifier $textDocument
     * @param string|null $text (NOTE: can't use ?T here)
     * @return void
     * @suppress PhanTypeMismatchArgument
     * @suppress PhanUnreferencedPublicMethod called by client via AdvancedJsonRpc
     */
    public function didSave(TextDocumentIdentifier $textDocument, string $text = null)
    {
        $this->file_mapping->addOverrideURI($textDocument->uri, $text);
        Logger::logInfo("Called didSave, uri={$textDocument->uri} len(text)=" . strlen($text ?? ''));
        $this->server->analyzeURIAsync($textDocument->uri);
    }

    /**
     * The document change notification is sent from the client to the server to signal changes to a text document.
     *
     * @param VersionedTextDocumentIdentifier $textDocument
     * @param TextDocumentContentChangeEvent[] $contentChanges
     * @return void
     * @suppress PhanUnreferencedPublicMethod called by client via AdvancedJsonRpc
     */
    public function didChange(VersionedTextDocumentIdentifier $textDocument, array $contentChanges)
    {
        foreach ($contentChanges as $change) {
            $this->file_mapping->addOverrideURI($textDocument->uri, $change->text);
        }
        Logger::logInfo("Called didChange, uri={$textDocument->uri} version={$textDocument->version}");
        $this->server->analyzeURIAsync($textDocument->uri);

        // TODO:   Maybe allow reloading .phan/config, at least the files and directories to parse/analyze
    }

    /**
     * The document close notification is sent from the client to the server when the document got closed in the client.
     * The document's truth now exists where the document's uri points to (e.g. if the document's uri is a file uri the
     * truth now exists on disk).
     *
     * @param TextDocumentIdentifier $textDocument The document that was closed
     * @return void
     * @suppress PhanUnreferencedPublicMethod called by client via AdvancedJsonRpc
     */
    public function didClose(TextDocumentIdentifier $textDocument)
    {
        $this->file_mapping->removeOverrideURI($textDocument->uri);
        $this->client->textDocument->publishDiagnostics(Utils::pathToUri(Utils::uriToPath($textDocument->uri)), []);
        Logger::logInfo("Called didClose, uri={$textDocument->uri}");
    }
}
