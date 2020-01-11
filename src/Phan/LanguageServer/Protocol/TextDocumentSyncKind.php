<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Defines how the host (editor) should sync document changes to the language server.
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/TextDocumentSyncKind.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
abstract class TextDocumentSyncKind
{
    /**
     * Documents should not be synced at all.
     * @suppress PhanUnreferencedPublicClassConstant (unused)
     */
    public const NONE = 0;

    /**
     * Documents are synced by always sending the full content of the document.
     */
    public const FULL = 1;

    /**
     * Documents are synced by sending the full content on open. After that only
     * incremental updates to the document are sent.
     * @suppress PhanUnreferencedPublicClassConstant (unused)
     */
    public const INCREMENTAL = 2;
}
