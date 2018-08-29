<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

use Phan\Config;
use Phan\Language\FileRef;
use Phan\LanguageServer\Utils;

/**
 * Represents a location inside a resource, such as a line inside a text file.
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/Location.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class Location
{
    /**
     * @var string|null
     */
    public $uri;

    /**
     * @var Range|null
     */
    public $range;

    public function __construct(string $uri = null, Range $range = null)
    {
        $this->uri = $uri;
        $this->range = $range;
    }

    /**
     * Callers should check $context->isPHPInternal() first
     */
    public static function fromContext(FileRef $context) : Location
    {
        $path = Config::projectPath($context->getFile());
        $uri = Utils::pathToUri($path);
        $range = Range::fromContextOnSingleLine($context);
        return new self($uri, $range);
    }

    public static function fromArray(array $data) : Location
    {
        return new self($data['uri'], Range::fromArray($data['range']));
    }
}
