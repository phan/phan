<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * TODO: Contribute to php-language-server?
 * Based on SaveOptions description in
 * https://microsoft.github.io/language-server-protocol/specification
 */
class SaveOptions
{
    /**
     * @var bool|null
     * The client is supposed to include the content on save.
     * @suppress PhanWriteOnlyPublicProperty (sent to client via AdvancedJsonRpc)
     */
    public $includeText;
}
