<?php

// Test for issue #4533: MoreSpecificElementTypePlugin should not warn about
// functions that can return empty arrays when the inferred type is array{}|non-empty-array

class TestEmptyArrayReturn {
    /**
     * This function can return an empty array.
     * @param string $path
     * @param string $baseurl
     * @param string $preg_match
     * @return array<string,string>
     */
    public static function getFiles($path, $baseurl, $preg_match = '/.*/'): array {
        $files = scandir($path, SCANDIR_SORT_ASCENDING);
        $list = [];

        foreach ($files as $file) {
            if (preg_match($preg_match, $file) && is_file($path . DIRECTORY_SEPARATOR . $file)) {
                $list[$file] = "$baseurl$file";
            }
        }
        // At this point, $list has type array{}|non-empty-array<string,string>
        // MoreSpecificElementTypePlugin should NOT warn about this
        return $list;
    }

    /**
     * Another example with a simpler pattern
     * @return array<int,string>
     */
    public static function filterItems(array $items, callable $filter): array {
        $result = [];
        foreach ($items as $item) {
            if ($filter($item)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * This should still warn because it never returns an empty array
     * @return array<int,string>
     */
    public static function alwaysHasItems(): array {
        return ['always', 'has', 'items'];
    }
}
