<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\CtagsPlugin;

/**
 * Represents a sorted set of unique ctags entries for definitions of elements
 */
class CtagsEntrySet
{
    /** @var array<string, CtagsEntry> */
    private $entries = [];
    /**
     * @return array<string, CtagsEntry> a sorted map of sorted entries.
     * This sorts the entries when it is called.
     */
    public function toArray(): array
    {
        \uksort($this->entries, 'strcmp');
        return $this->entries;
    }

    /**
     * Record an occurrence of an element definition.
     */
    public function add(CtagsEntry $entry): void
    {
        if (!$entry->isValid()) {
            return;
        }
        $lookup = $entry->name . "\0" . $entry->getOrderKey() . "\0" . $entry;
        $this->entries[$lookup] = $entry;
    }
}
