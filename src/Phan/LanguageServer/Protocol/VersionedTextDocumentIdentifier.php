<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/VersionedTextDocumentIdentifier.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 * @phan-immutable
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
