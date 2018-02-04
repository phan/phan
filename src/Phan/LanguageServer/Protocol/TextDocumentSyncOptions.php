<?php

namespace Phan\LanguageServer\Protocol;

/**
 * TODO: Contribute to php-language-server?
 * Based on TextDocumentSyncOptions description in
 * https://microsoft.github.io/language-server-protocol/specification
 */
class TextDocumentSyncOptions
{
    /**
     * @var bool|null
     * Open and close notifications are sent to the server.
     */
    public $openClose;
    /**
     * @var int|null
     * Change notifications are sent to the server. See TextDocumentSyncKind.None, TextDocumentSyncKind.Full
     * and TextDocumentSyncKindIncremental.
     */
    public $change;

    /**
     * @var bool|null
     * Will save notifications get sent to the server.
     */
    public $willSave;

    /**
     * @var bool|null
     * Will save wait until requests get sent to the server.
     */
    public $willSaveWaitUntil;

    /**
     * @var SaveOptions|null
     * Save notifications are sent to the server.
     */
    public $save;
}
