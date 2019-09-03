<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * An event describing a file change.
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/FileEvent.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 * @phan-immutable
 */
class FileEvent
{
    /**
     * The file's URI.
     *
     * @var string
     */
    public $uri;

    /**
     * The change type.
     *
     * @var int
     */
    public $type;

    /**
     * @param string $uri
     * @param int $type
     */
    public function __construct(string $uri, int $type)
    {
        $this->uri = $uri;
        $this->type = $type;
    }
}
