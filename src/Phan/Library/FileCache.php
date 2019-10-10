<?php declare(strict_types=1);

namespace Phan\Library;

use RuntimeException;

/**
 * An LRU cache for the contents of files (FileCacheEntry), and data structures derived from contents of files.
 */
final class FileCache
{
    const MINIMUM_CACHE_SIZE = 20;
    /**
     * @var int - Maximum cache size
     */
    private static $max_size;

    /**
     * @var array<string,FileCacheEntry> - An ordered php associative array, with most recently used at the end of the array.
     */
    private static $cache_entries = [];

    /**
     * Sets the cache size to $max_size (or self::MINIMUM_CACHE_SIZE if that's larger).
     * Entries will be removed until there are $max_size or fewer entries.
     */
    public static function setMaxCacheSize(int $max_size) : void
    {
        self::$max_size = \max($max_size, self::MINIMUM_CACHE_SIZE);
        while (\count(self::$cache_entries) > self::$max_size) {
            \array_shift(self::$cache_entries);
        }
    }

    /**
     * Adds an entry recording that $file_name has contents $file_contents,
     * overwriting any previous entries
     */
    public static function addEntry(string $file_name, string $contents) : FileCacheEntry
    {
        $old_entry = self::$cache_entries[$file_name] ?? null;
        if ($old_entry) {
            unset(self::$cache_entries[$file_name]);
            if ($old_entry->getContents() === $contents) {
                // If the contents didn't change, keep the cache entry and move it to the end (Most recently used).
                self::$cache_entries[$file_name] = $old_entry;
                return $old_entry;
            }
        }
        $entry = new FileCacheEntry($contents);
        self::$cache_entries[$file_name] = $entry;
        if (\count(self::$cache_entries) > self::$max_size) {
            // Ensure that the size <= self::$max_size. Remove the least recently used entry (front of list).
            \array_shift(self::$cache_entries);
        }
        return $entry;
    }

    /**
     * @return ?FileCacheEntry if the entry exists in cache, return it.
     * Otherwise, return null.
     */
    public static function getEntry(string $file_name) : ?FileCacheEntry
    {
        $entry = self::$cache_entries[$file_name] ?? null;
        if ($entry) {
            // Move the entry to the end (most recently used) and return it.
            unset(self::$cache_entries[$file_name]);
            self::$cache_entries[$file_name] = $entry;
            return $entry;
        }
        return null;
    }

    /**
     * @param string $file_name an absolute path to a file on disk
     * @return FileCacheEntry This will load the file from the filesystem if it could not be found.
     * @throws RuntimeException if the file could not be loaded
     */
    public static function getOrReadEntry(string $file_name) : FileCacheEntry
    {
        $entry = self::getEntry($file_name);
        if ($entry !== null) {
            return $entry;
        }
        if (!\file_exists($file_name)) {
            throw new RuntimeException("FileCache::getOrReadEntry: unable to find '$file_name'\n");
        }
        if (!\is_readable($file_name)) {
            throw new RuntimeException("FileCache::getOrReadEntry: unable to read '$file_name'\n");
        }
        $contents = \file_get_contents($file_name);
        if (!\is_string($contents)) {
            throw new RuntimeException("FileCache::getOrReadEntry: file_get_contents failed for '$file_name'\n");
        }
        $entry = self::addEntry($file_name, $contents);
        return $entry;
    }

    /**
     * Clear the cache (E.g. after pausing, accepting a daemon mode request, then resuming)
     */
    public static function clear() : void
    {
        self::$cache_entries = [];
    }

    /**
     * @return list<string> list of file paths with most recently used entries at the end.
     */
    public static function getCachedFileList() : array
    {
        return \array_keys(self::$cache_entries);
    }
}
