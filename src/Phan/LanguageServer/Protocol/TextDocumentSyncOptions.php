<?php
declare(strict_types=1);

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
     * @suppress PhanWriteOnlyPublicProperty this is serialized and sent to the client
     */
    public $openClose;

    /**
     * @var int|null
     * Change notifications are sent to the server. See TextDocumentSyncKind.None, TextDocumentSyncKind.Full
     * and TextDocumentSyncKindIncremental.
     * @suppress PhanWriteOnlyPublicProperty this is serialized and sent to the client
     */
    public $change;

    /**
     * @var bool|null
     * Will save notifications get sent to the server.
     * @suppress PhanUnreferencedPublicProperty this is serialized and sent to the client
     */
    public $willSave;

    /**
     * @var bool|null
     * Will save wait until requests get sent to the server.
     * @suppress PhanUnreferencedPublicProperty this is serialized and sent to the client
     */
    public $willSaveWaitUntil;

    /**
     * @var SaveOptions|null
     * Save notifications are sent to the server.
     * @suppress PhanWriteOnlyPublicProperty
     */
    public $save;
}
