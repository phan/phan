<?php

namespace Phan\LanguageServer\Protocol;

/**
 * Based on https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/ServerCapabilities.php
 * FIXME: Hook up capabilities for
 */
class ServerCapabilities
{
    /**
     * Defines how text documents are synced.
     *
     * @var int|null
     */
    public $textDocumentSync;

    /**
     * FIXME: Make the server provide completion support, and re-integrate work on TysonAndre/php-parser-to-php-ast
     *
     * @var CompletionOptions|null
     */
    // public $completionProvider;

    /**
     * The server provides find references support.
     *
     * @var bool|null
     */
    // public $referencesProvider;

    /**
     * The server provides document highlight support.
     *
     * @var bool|null
     */
    // public $documentHighlightProvider;

    /**
     * The server provides document symbol support.
     *
     * @var bool|null
     */
    // public $documentSymbolProvider;

    /**
     * The server provides workspace symbol support.
     *
     * @var bool|null
     */
    // public $workspaceSymbolProvider;

    /**
     * The server provides code actions.
     *
     * @var bool|null
     */
    //public $codeActionProvider;

    /**
     * The server provides code lens.
     *
     * @var CodeLensOptions|null
     */
    //public $codeLensProvider;

    /**
     * The server provides document formatting.
     *
     * @var bool|null
     */
    //public $documentFormattingProvider;

    /**
     * The server provides document range formatting.
     *
     * @var bool|null
     */
    //public $documentRangeFormattingProvider;

    /**
     * The server provides document formatting on typing.
     *
     * @var DocumentOnTypeFormattingOptions|null
     */
    //public $documentOnTypeFormattingProvider;

    /**
     * The server provides rename support.
     *
     * @var bool|null
     */
    //public $renameProvider;

    /**
     * The server provides workspace references exporting support.
     *
     * @var bool|null
     */
    //public $xworkspaceReferencesProvider;

    /**
     * The server provides extended text document definition support.
     *
     * @var bool|null
     */
    //public $xdefinitionProvider;

    /**
     * TODO: The server provides workspace dependencies support.
     *
     * @var bool|null
     */
    //public $dependenciesProvider;
}
