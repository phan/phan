<?php

declare(strict_types=1);

namespace Phan\Library\IncrementalAnalysis;

/**
 * Tracks dependencies between files and FQSENs.
 *
 * This class maintains an in-memory map of which files declare,
 * extend, or implement which FQSENs.
 *
 * NOTE: This tracks only structural dependencies (class declarations and
 * inheritance relationships), not usage dependencies (function calls,
 * method calls, property accesses, etc.). For those, namespace-level
 * invalidation is used as a conservative strategy.
 *
 * This class has ZERO dependencies on Phan core - it's a pure
 * data structure.
 */
class DependencyTracker
{
    /** @var array<string,array{declares:list<string>,extends:list<string>,implements:list<string>}> */
    private static $file_dependencies = [];

    /** @var ?string Current file being tracked */
    private static $current_file = null;

    /**
     * Start tracking dependencies for a file
     *
     * @param string $file_path Absolute path to file
     */
    public static function startFile(string $file_path): void
    {
        self::$current_file = $file_path;
        self::$file_dependencies[$file_path] = [
            'declares' => [],
            'extends' => [],
            'implements' => [],
        ];
    }

    /**
     * Stop tracking dependencies for current file
     */
    public static function endFile(): void
    {
        self::$current_file = null;
    }

    /**
     * Track a dependency from current file to an FQSEN
     *
     * @param string $fqsen The FQSEN being referenced
     * @param string $type One of: 'declares', 'extends', 'implements'
     */
    public static function track(string $fqsen, string $type): void
    {
        if (self::$current_file === null) {
            return; // Not tracking any file
        }

        if (!isset(self::$file_dependencies[self::$current_file][$type])) {
            return; // Invalid type
        }

        // Add to list (will deduplicate later)
        self::$file_dependencies[self::$current_file][$type][] = $fqsen;
    }

    /**
     * Get dependencies for a file
     *
     * @return array{declares:list<string>,extends:list<string>,implements:list<string>}
     */
    public static function getDependencies(string $file_path): array
    {
        if (!isset(self::$file_dependencies[$file_path])) {
            return [
                'declares' => [],
                'extends' => [],
                'implements' => [],
            ];
        }

        // Deduplicate each type
        $deps = self::$file_dependencies[$file_path];
        return [
            'declares' => \array_values(\array_unique($deps['declares'])),
            'extends' => \array_values(\array_unique($deps['extends'])),
            'implements' => \array_values(\array_unique($deps['implements'])),
        ];
    }

    /**
     * Clear all tracked dependencies
     */
    public static function reset(): void
    {
        self::$file_dependencies = [];
        self::$current_file = null;
    }

    /**
     * Get all tracked files
     *
     * @return list<string>
     */
    public static function getAllFiles(): array
    {
        return \array_keys(self::$file_dependencies);
    }
}
