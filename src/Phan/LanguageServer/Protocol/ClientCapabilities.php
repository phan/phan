<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/ClientCapabilities.php
 */
class ClientCapabilities
{
    /**
     * The client supports workspace/xfiles requests
     *
     * @var bool|null
     * @suppress PhanUnreferencedPublicProperty may use eventually
     */
    public $xfilesProvider;

    /**
     * The client supports textDocument/xcontent requests
     *
     * @var bool|null
     * @suppress PhanUnreferencedPublicProperty may use eventually
     */
    public $xcontentProvider;

    /**
     * The client supports xcache/* requests
     *
     * @var bool|null
     * @suppress PhanUnreferencedPublicProperty may use eventually
     */
    public $xcacheProvider;
}
