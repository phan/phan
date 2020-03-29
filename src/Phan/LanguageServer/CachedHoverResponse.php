<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

use Phan\LanguageServer\Protocol\Hover;
use Phan\LanguageServer\Protocol\Position;

/**
 * Caches the data for a hover response and information to check if the request was equivalent.
 *
 * @phan-read-only
 */
class CachedHoverResponse
{
    /** @var string the URI of the file where the hover cursor was. */
    private $uri;
    /** @var Position the position in the file where the hover cursor was. */
    private $position;
    /** @var string a hash of the FileMapping for open files in the editor. */
    private $file_mapping_hash;
    /** @var ?Hover the cached hover response */
    private $hover;

    public function __construct(string $uri, Position $position, FileMapping $file_mapping, ?Hover $hover)
    {
        $this->uri = $uri;
        $this->position = $position;
        $this->file_mapping_hash = $file_mapping->getHash();
        $this->hover = $hover;
    }

    /**
     * Checks if this is effectively the same request as the previous request
     */
    public function isSameRequest(string $uri, Position $position, FileMapping $file_mapping): bool
    {
        return $this->uri === $uri && $this->position->compare($position) === 0 && $this->file_mapping_hash === $file_mapping->getHash();
    }

    /**
     * Get the hover response data or null.
     * Callers should check that isSameRequest() is true first.
     */
    public function getHoverResponse(): ?Hover
    {
        return $this->hover;
    }
}
