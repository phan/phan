<?php

declare(strict_types=1);

namespace Phan\Library;

use InvalidArgumentException;

/**
 * A cache from string keys to object instances, with objects saved as files on disk.
 *
 * @implements Cache<T>
 * @template T
 */
class DiskCache implements Cache
{
    /** @var string absolute path to a temporary directory with serialized data */
    private $directory;

    /** @var string */
    private $suffix;

    /** @var class-string<T> */
    private $class_name;

    /** @var bool */
    private $use_igbinary;

    /** @var ?bool */
    private $directory_exists;

    /**
     * @param class-string<T> $class_name
     */
    public function __construct(string $directory, string $suffix, $class_name, bool $use_igbinary)
    {
        $this->directory = $directory;
        $this->suffix = $suffix;
        $this->class_name = $class_name;
        $this->use_igbinary = $use_igbinary;
    }

    /**
     * Returns the path to the file used to cache $cache_key
     */
    public function getPath(string $cache_key): string
    {
        return $this->directory . '/' . $cache_key . ($this->use_igbinary ? '-ig' : '') . $this->suffix;
    }

    /**
     * Retrieve a copy of the value from the disk cache, or return null
     *
     * @return ?T
     */
    public function getIfExists(string $key)
    {
        $path = $this->getPath($key);
        if (!\file_exists($path)) {
            return null;
        }
        $contents = \file_get_contents($path);
        if (!\is_string($contents)) {
            return null;
        }
        if ($this->use_igbinary) {
            if (\strncmp($contents, "\x00\x00\x00\x02", 4) !== 0) {
                \fwrite(\STDERR, "Saw invalid igbinary serialized data at $path: wrong header\n");
                return null;
            }
            return \igbinary_unserialize($contents);
        } else {
            return \unserialize($contents);
        }
    }

    private function ensureDirectoryExists(): bool
    {
        if ($this->directory_exists === null) {
            $this->directory_exists = false;
            if (!\is_dir($this->directory)) {
                if (!\mkdir($this->directory, 0755, true)) {
                    \fwrite(\STDERR, "Failed to create AST cache directory $this->directory\n");
                    return false;
                }
            }
            $this->directory_exists = true;
        }
        return $this->directory_exists ?? false;
    }

    /**
     * Save an entry with cache key $key and value $value to disk
     * @param T $value
     * @return bool true if successfully saved
     */
    public function save(string $key, $value): bool
    {
        if (!$this->ensureDirectoryExists()) {
            return false;
        }

        $class_name = $this->class_name;
        if (!($value instanceof $class_name)) {
            throw new InvalidArgumentException("Expected to cache an instance of $class_name, got " . (\is_object($value) ? \get_class($value) : \gettype($value)));
        }
        if ($this->use_igbinary) {
            $contents = \igbinary_serialize($value);
        } else {
            $contents = \serialize($value);
        }
        if (!\is_string($contents)) {
            return false;
        }
        $path = $this->getPath($key);
        // XXX save and rename to be atomic
        return \file_put_contents($path, $contents) !== false;
    }
}
