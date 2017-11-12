<?php declare(strict_Types=1);
namespace Phan\Library;

/**
 * An LRU cache for the contents of files (FileCacheEntry), and data structures derived from contents of files.
 */
final class FileCache
{
    const MINIMUM_CACHE_SIZE = 5;
    /**
     * @var int - Maximum cache size
     */
    private static $max_size;

    /**
     * @var FileCacheEntry[] - An ordered php associative array, with most recently used at the end of the array.
     */
    private static $cache_entries = [];

    public static function setMaxCacheSize(int $max_size)
    {
        self::$max_size = max($max_size, self::MINIMUM_CACHE_SIZE);
        while (\count(self::$cache_entries) > self::$max_size) {
            \array_shift(self::$cache_entries);
        }
    }

    /**
     * @return FileCacheEntry
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
     * @return ?FileCacheEntry
     */
    public static function getEntry(string $file_name)
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
     * @throws \RuntimeException if the file could not be loaded
     * @return FileCacheEntry
     */
    public static function getOrReadEntry(string $file_name) : FileCacheEntry
    {
        $entry = self::getEntry($file_name);
        if ($entry !== null) {
            return $entry;
        }
        if (!\file_exists($file_name)) {
            throw new \RuntimeException("FileCache::getOrReadEntry: unable to find '$file_name'\n");
        }
        if (!\is_readable($file_name)) {
            throw new \RuntimeException("FileCache::getOrReadEntry: unable to read '$file_name'\n");
        }
        $contents = file_get_contents($file_name);
        if (!\is_string($contents)) {
            throw new \RuntimeException("FileCache::getOrReadEntry: file_get_contents failed for '$file_name'\n");
        }
        $entry = self::addEntry($file_name, $contents);
        return $entry;
    }

    /**
     * Clear the cache (E.g. after pausing, accepting a daemon mode request, then resuming)
     * @return void
     */
    public static function clear()
    {
        self::$cache_entries = [];
    }

    /**
     * @return string[] list of file paths with most recently used entries at the end.
     */
    public static function getCachedFileList() : array
    {
        return array_keys(self::$cache_entries);
    }
}
