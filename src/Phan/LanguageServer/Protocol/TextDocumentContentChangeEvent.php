<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * An event describing a change to a text document. If range and rangeLength are omitted
 * the new text is considered to be the full content of the document.
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/TextDocumentContentChangeEvent.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 * @phan-immutable
 */
class TextDocumentContentChangeEvent
{
    /**
     * The range of the document that changed.
     *
     * @var Range|null
     * @suppress PhanUnreferencedPublicProperty (We don't support partial updates, yet)
     */
    public $range;

    /**
     * The length of the range that got replaced.
     *
     * @var int|null
     * @suppress PhanUnreferencedPublicProperty (We don't support partial updates, yet)
     */
    public $rangeLength;

    /**
     * The new text of the document.
     *
     * @var string
     */
    public $text;
}
