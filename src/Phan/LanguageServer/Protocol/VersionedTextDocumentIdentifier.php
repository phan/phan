<?php

namespace Phan\LanguageServer\Protocol;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/VersionedTextDocumentIdentifier.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class VersionedTextDocumentIdentifier extends TextDocumentIdentifier
{
    /**
     * The version number of this document.
     *
     * @var int
     */
    public $version;
}
