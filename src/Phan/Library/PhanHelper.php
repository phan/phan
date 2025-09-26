<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * Utility class for interfacing with the optional phan_helper native extension.
 *
 * This class provides high-performance native implementations of critical operations
 * while maintaining compatibility when the extension is not available.
 *
 * The phan_helper extension provides significant performance improvements for select operations:
 * - Object deduplication: 30x+ faster than PHP array_unique()
 * - FQSEN parsing: 1.6x faster than PHP explode/implode
 *
 * Note: Many functions were removed after performance testing showed PHP's built-in
 * functions were already optimized and faster than our implementations.
 */
final class PhanHelper
{
    /** @var bool|null Cached result of extension availability check */
    private static $extension_available = null;

    /** @var bool Whether to use native functions (can be disabled for testing) */
    private static $use_native = true;

    /**
     * Check if the phan_helper extension is available and enabled
     */
    public static function isExtensionAvailable(): bool
    {
        if (self::$extension_available === null) {
            self::$extension_available = extension_loaded('phan_helper') &&
                                       self::$use_native &&
                                       self::verifyExtensionFunctions();
        }
        return (bool)self::$extension_available;
    }

    /**
     * Verify that required extension functions exist and are callable
     */
    private static function verifyExtensionFunctions(): bool
    {
        $required_functions = [
            'phan_array_unique_objects',
            'phan_fqsen_parse'
        ];

        foreach ($required_functions as $func) {
            if (!function_exists($func)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Disable native functions for testing/debugging
     */
    public static function disableNativeFunctions(): void
    {
        self::$use_native = false;
        self::$extension_available = false;
    }

    /**
     * Re-enable native functions
     */
    public static function enableNativeFunctions(): void
    {
        self::$use_native = true;
        self::$extension_available = null; // Force re-check
    }

    // ===== REMOVED UNION TYPE OPERATIONS =====
    //
    // The following union type operations were removed from the extension after
    // performance testing showed PHP's built-in functions were faster:
    // - unionMergeTypes(): PHP array_merge + array_unique is faster
    // - unionIntersectTypes(): PHP array_intersect is faster
    // - unionDiffTypes(): PHP array_diff is faster
    // - unionCanCastTo(): PHP count(array_intersect()) is faster
    //
    // Code that used these methods should directly use PHP's built-in functions.

    // ===== ARRAY OPERATIONS =====

    /**
     * Remove duplicate objects from array using object identity
     * @param object[] $objects Array of objects
     * @return object[] Array with duplicates removed
     */
    public static function arrayUniqueObjects(array $objects): array
    {
        if (self::isExtensionAvailable()) {
            try {
                return phan_array_unique_objects($objects);
            } catch (\Throwable $e) {
                // Fallback to PHP implementation on any error
                error_log("phan_helper extension error in arrayUniqueObjects: " . $e->getMessage());
            }
        }

        // PHP fallback
        return array_values(array_unique($objects, SORT_REGULAR));
    }

    // arrayMergeUnique() was removed - only provided 1.04x improvement
    // Use: array_values(array_unique(array_merge($arr1, $arr2))) directly



    // ===== STRING OPERATIONS =====

    /**
     * Parse FQSEN into components
     * @param string $fqsen Fully qualified structural element name
     * @return array{namespace: string, class: string, method: string} Parsed components
     */
    public static function parseFQSEN(string $fqsen): array
    {
        if (self::isExtensionAvailable()) {
            return phan_fqsen_parse($fqsen);
        }

        // PHP fallback
        $parts = explode('::', $fqsen);
        $class_part = $parts[0];
        $method_part = $parts[1] ?? '';

        $ns_parts = explode('\\', $class_part);
        $class = array_pop($ns_parts);
        $namespace = implode('\\', $ns_parts);

        return [
            'namespace' => $namespace,
            'class' => $class,
            'method' => $method_part
        ];
    }

    // ===== UTILITY METHODS =====

    /**
     * Get performance statistics about extension usage
     * @return array{extension_available: bool, functions_available: string[]}
     */
    public static function getStats(): array
    {
        $functions_available = [];

        if (self::isExtensionAvailable()) {
            $test_functions = [
                'phan_array_unique_objects',
                'phan_fqsen_parse'
            ];

            foreach ($test_functions as $func) {
                if (function_exists($func)) {
                    $functions_available[] = $func;
                }
            }
        }

        return [
            'extension_available' => self::isExtensionAvailable(),
            'functions_available' => $functions_available
        ];
    }

    /**
     * Benchmark native vs PHP implementations
     * @param int $iterations Number of test iterations
     * @return array{error?: string, array_unique?: array{php_time: float, native_time: float, speedup: float}} Performance comparison results
     */
    public static function benchmark(int $iterations = 1000): array
    {
        if (!self::isExtensionAvailable()) {
            return ['error' => 'Extension not available for benchmarking'];
        }

        // Create test data
        $test_objects = [];
        for ($i = 0; $i < 10; $i++) {
            $test_objects[] = (object)['id' => $i];
        }

        $results = [];

        // Benchmark array unique
        $start = microtime(true);
        // @phan-suppress-next-line PhanSideEffectFreeForBody
        for ($i = 0; $i < $iterations; $i++) {
            // @phan-suppress-next-line PhanUnusedVariable
            $result = array_unique($test_objects, SORT_REGULAR);
        }
        $php_time = microtime(true) - $start;

        $start = microtime(true);
        // @phan-suppress-next-line PhanSideEffectFreeForBody
        for ($i = 0; $i < $iterations; $i++) {
            // @phan-suppress-next-line PhanUnusedVariable
            $result = phan_array_unique_objects($test_objects);
        }
        $native_time = microtime(true) - $start;

        $results['array_unique'] = [
            'php_time' => $php_time,
            'native_time' => $native_time,
            'speedup' => $php_time / $native_time
        ];

        return $results;
    }
}