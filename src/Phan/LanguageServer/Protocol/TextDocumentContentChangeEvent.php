<?php

namespace Phan\LanguageServer\Protocol;

/**
 * An event describing a change to a text document. If range and rangeLength are omitted
 * the new text is considered to be the full content of the document.
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/TextDocumentContentChangeEvent.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class TextDocumentContentChangeEvent
{
    /**
     * The range of the document that changed.
     *
     * @var Range|null
     */
    public $range;

    /**
     * The length of the range that got replaced.
     *
     * @var int|null
     */
    public $rangeLength;

    /**
     * The new text of the document.
     *
     * @var string
     */
    public $text;
}
