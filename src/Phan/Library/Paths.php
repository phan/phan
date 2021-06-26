<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * Utilities for working with paths
 */
class Paths
{
    /**
     * @return bool
     * Is the passed in path already an absolute path?
     */
    public static function isAbsolutePath(string $path): bool
    {
        $first_character = \substr($path, 0, 1);
        // Make sure it's actually relative
        if (\DIRECTORY_SEPARATOR === $first_character || '/' === $first_character) {
            return true;
        }
        // Check for absolute path in windows, e.g. C:\
        if (\DIRECTORY_SEPARATOR === "\\" &&
                \strlen($path) > 3 &&
                \ctype_alpha($first_character) &&
                $path[1] === ':' &&
                \strspn($path, '/\\', 2, 1)) {
            return true;
        }
        return false;
    }


    /**
     * Returns $path as an absolute path using $absolute_path as the starting folder.
     * Returns $path unmodified if $path is already absolute.
     *
     * @param string $absolute_directory the path to use when converting $path from a relative path to an absolute path.
     * @param string $path a relative or absolute path
     */
    public static function toAbsolutePath(string $absolute_directory, string $path): string
    {
        if (Paths::isAbsolutePath($path)) {
            return $path;
        }
        $path = \preg_replace('@^(\.[\\\\/]+)+@', '', $path);
        if ($path === '.') {
            return $absolute_directory;
        }

        return $absolute_directory . \DIRECTORY_SEPARATOR .  $path;
    }

    /**
     * Returns JSON encoded $path, without escaping unicode or "/"
     */
    public static function escapePathForIssue(string $path): string
    {
        // If possible, use json_encode.
        // If json_encode failed (e.g. invalid unicode), then use var_representation
        return \json_encode($path, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) ?: \var_representation($path);
    }
}
