<?php

declare(strict_types=1);

namespace Phan\Library;

use Phan\Config;
use Phan\Language\FileRef;

/**
 * Tells which package a file of the project belongs to, for `internal_access_scope` set to `'package'`.
 *
 * - A file in the vendor directory belongs to the Composer package that is installed in its directory
 *   (from `composer/installed.json` in that directory; without it, the package is the directory `vendor/name/package`).
 * - A file in another directory of `exclude_analysis_directory_list` belongs to that directory:
 *   code that is parsed but not analyzed is code of somebody else.
 * - Any other file of the project belongs to the project.
 *
 * This does not depend on how the classes are loaded (`psr-4`, `classmap`, ...) or on the namespaces.
 *
 * @internal
 */
final class ComposerPackages
{
    /** @var ?string the project root directory that the directories below were loaded for */
    private static $loaded_root = null;

    /** @var string the vendor directory, relative to the project root, with a trailing slash ('' if it is not in the project) */
    private static $vendor_dir = '';

    /** @var array<string,string> maps the directory of an installed package (relative, with a trailing slash) to its name, longest first */
    private static $package_dirs = [];

    /** @var list<string> the other directories of `exclude_analysis_directory_list` (relative, with a trailing slash), longest first */
    private static $excluded_dirs = [];

    /** @var array<string,string> the package of a file ('' for none), for the path that a FileRef has */
    private static $cache = [];

    /** The package of the files of the project itself */
    private const ROOT_PACKAGE = '(project)';

    /**
     * Forgets what was loaded (for tests, and when the project or its packages change).
     */
    public static function reset(): void
    {
        self::$loaded_root = null;
        self::$vendor_dir = '';
        self::$package_dirs = [];
        self::$excluded_dirs = [];
        self::$cache = [];
    }

    /**
     * @param string $file the path of a file, as a FileRef has it
     * @return ?string the package that the file belongs to, or null for a file that is not in the project
     * (such as the stubs of Phan for internal functions)
     */
    public static function packageOfFile(string $file): ?string
    {
        $root = Config::getProjectRootDirectory();
        if (self::$loaded_root !== $root) {
            self::load($root);
        }
        $package = self::$cache[$file] ?? null;
        if ($package === null) {
            // ('' for a file that is not in the project)
            $package = '';
            $path = \str_replace('\\', '/', FileRef::getProjectRelativePathForPath($file));
            if ($path !== '' && $path[0] !== '/' && !\str_starts_with($path, '../') && !\preg_match('@^[a-zA-Z]:/@', $path)) {
                $slash = \strrpos($path, '/');
                $package = self::packageOfDirectory($slash === false ? '' : \substr($path, 0, $slash + 1));
            }
            self::$cache[$file] = $package;
        }
        return $package === '' ? null : $package;
    }

    /**
     * @param string $dir a directory relative to the project root, with a trailing slash ('' for the root)
     */
    private static function packageOfDirectory(string $dir): string
    {
        $vendor_dir = self::$vendor_dir;
        if ($vendor_dir !== '' && \str_starts_with($dir, $vendor_dir)) {
            foreach (self::$package_dirs as $package_dir => $name) {
                if (\str_starts_with($dir, $package_dir)) {
                    return $name;
                }
            }
            // Not a package of installed.json: the directory `vendor/name/package`, or what there is of it.
            $parts = \explode('/', \substr($dir, \strlen($vendor_dir)), 3);
            return 'vendor:' . \strtolower($parts[0] . '/' . ($parts[1] ?? ''));
        }
        foreach (self::$excluded_dirs as $excluded_dir) {
            if (\str_starts_with($dir, $excluded_dir)) {
                return 'excluded:' . $excluded_dir;
            }
        }
        return self::ROOT_PACKAGE;
    }

    private static function load(string $root): void
    {
        self::reset();
        self::$loaded_root = $root;
        $composer = self::readJson($root . '/composer.json') ?? [];
        $vendor_path = self::vendorPath($root, $composer);
        self::$vendor_dir = self::relativeDirectory($root, $vendor_path) ?? '';

        $installed = self::readJson($vendor_path . '/composer/installed.json');
        // (Composer 2 has the list in 'packages', Composer 1 is the list)
        $packages = $installed['packages'] ?? $installed ?? [];
        $package_dirs = [];
        foreach (\is_array($packages) ? $packages : [] as $package) {
            $name = \is_array($package) ? ($package['name'] ?? null) : null;
            if (!\is_array($package) || !\is_string($name) || $name === '') {
                continue;
            }
            // (The names of Composer packages are not case-sensitive)
            $name = \strtolower($name);
            // Composer 2 has the directory of the package, relative to `vendor/composer`.
            $install_path = $package['install-path'] ?? null;
            $package_path = \is_string($install_path) && $install_path !== ''
                ? (Paths::isAbsolutePath($install_path) ? $install_path : $vendor_path . '/composer/' . $install_path)
                : $vendor_path . '/' . $name;
            $package_dir = self::relativeDirectory($root, $package_path);
            if ($package_dir !== null && $package_dir !== '') {
                $package_dirs[$package_dir] = $name;
            }
        }
        // The longest directory first: a package can be installed in a directory of another one.
        \uksort($package_dirs, static function (string $a, string $b): int {
            return \strlen($b) <=> \strlen($a) ?: \strcmp($a, $b);
        });
        self::$package_dirs = $package_dirs;

        $excluded_dirs = [];
        foreach (Config::getValue('exclude_analysis_directory_list') as $excluded_dir) {
            if (!\is_string($excluded_dir)) {
                continue;
            }
            $excluded_dir = self::relativeDirectory($root, Paths::isAbsolutePath($excluded_dir) ? $excluded_dir : $root . '/' . $excluded_dir);
            if ($excluded_dir !== null && $excluded_dir !== '' && $excluded_dir !== self::$vendor_dir) {
                $excluded_dirs[] = $excluded_dir;
            }
        }
        \usort($excluded_dirs, static function (string $a, string $b): int {
            return \strlen($b) <=> \strlen($a) ?: \strcmp($a, $b);
        });
        self::$excluded_dirs = $excluded_dirs;
    }

    /**
     * @return ?string $path relative to $root, with forward slashes, without `.` and `..`, and with a trailing slash
     * ('' for $root itself), or null if it is not in $root
     */
    private static function relativeDirectory(string $root, string $path): ?string
    {
        $parts = [];
        foreach (\explode('/', \str_replace('\\', '/', $path)) as $part) {
            if ($part === '..') {
                \array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }
        $root_parts = [];
        foreach (\explode('/', \str_replace('\\', '/', $root)) as $part) {
            if ($part !== '' && $part !== '.') {
                $root_parts[] = $part;
            }
        }
        if (\array_slice($parts, 0, \count($root_parts)) !== $root_parts) {
            return null;
        }
        $relative = \implode('/', \array_slice($parts, \count($root_parts)));
        return $relative === '' ? '' : $relative . '/';
    }

    /**
     * @return string a hash of the files that the packages are read from (for the manifest of incremental analysis:
     * the issues of a file depend on these, with `internal_access_scope` set to `'package'`)
     */
    public static function metadataHash(): string
    {
        $root = Config::getProjectRootDirectory();
        $parts = [];
        foreach ([$root . '/composer.json', self::vendorPath($root, self::readJson($root . '/composer.json') ?? []) . '/composer/installed.json'] as $path) {
            $parts[] = \is_file($path) && \is_readable($path) ? (string)\hash_file('sha256', $path) : '';
        }
        return \implode(':', $parts);
    }

    /**
     * @param array<mixed,mixed> $composer the contents of the composer.json of the project
     * @return string the directory of the installed packages (`config.vendor-dir`, by default `vendor`)
     */
    private static function vendorPath(string $root, array $composer): string
    {
        $vendor_dir = $composer['config']['vendor-dir'] ?? 'vendor';
        if (!\is_string($vendor_dir) || $vendor_dir === '') {
            $vendor_dir = 'vendor';
        }
        return Paths::isAbsolutePath($vendor_dir) ? $vendor_dir : $root . '/' . $vendor_dir;
    }

    /**
     * @return ?array<mixed,mixed>
     */
    private static function readJson(string $path): ?array
    {
        if (!\is_file($path) || !\is_readable($path)) {
            return null;
        }
        $contents = \file_get_contents($path);
        if (!\is_string($contents)) {
            return null;
        }
        $data = \json_decode($contents, true);
        return \is_array($data) ? $data : null;
    }
}
