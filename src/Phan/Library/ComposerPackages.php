<?php

declare(strict_types=1);

namespace Phan\Library;

use Phan\Config;

/**
 * Tells which Composer package a namespace belongs to, from the autoload prefixes
 * of the `composer.json` of the project and of the packages that are installed.
 *
 * Used for `internal_access_scope` set to `'package'`.
 *
 * @internal
 */
final class ComposerPackages
{
    /** @var ?string the project root directory that $prefixes was loaded for */
    private static $loaded_root = null;

    /** @var array<string,string> maps a namespace prefix (lower case, with a trailing backslash) to the name of its package, longest prefix first */
    private static $prefixes = [];

    /** @var array<string,string> the package of a namespace ('' for none), for the namespace with a trailing backslash in lower case */
    private static $cache = [];

    /** The name that is used for the package of the project when its composer.json has no name */
    private const ROOT_PACKAGE = '(root)';

    /**
     * Forgets the prefixes that were loaded (for tests, and when the project changes).
     */
    public static function reset(): void
    {
        self::$loaded_root = null;
        self::$prefixes = [];
        self::$cache = [];
    }

    /**
     * @return ?string the name of the Composer package with the longest autoload prefix that $namespace is in,
     * or null if it is in none (or the project has no composer.json).
     * The namespaces of `autoload` and of `autoload-dev` of the project are one package.
     */
    public static function packageOfNamespace(string $namespace): ?string
    {
        $root = Config::getProjectRootDirectory();
        if (self::$loaded_root !== $root) {
            self::reset();
            self::$loaded_root = $root;
            self::$prefixes = self::loadPrefixes($root);
        }
        if (!self::$prefixes) {
            return null;
        }
        $key = \strtolower(\trim($namespace, '\\')) . '\\';
        $package = self::$cache[$key] ?? null;
        if ($package === null) {
            // ('' for a namespace that is in no package)
            $package = '';
            foreach (self::$prefixes as $prefix => $name) {
                if (\str_starts_with($key, $prefix)) {
                    $package = $name;
                    break;
                }
            }
            self::$cache[$key] = $package;
        }
        return $package === '' ? null : $package;
    }

    /**
     * @return array<string,string>
     */
    private static function loadPrefixes(string $root): array
    {
        $prefixes = [];
        $composer = self::readJson($root . '/composer.json');
        if ($composer === null) {
            return [];
        }
        $root_name = \is_string($composer['name'] ?? null) ? $composer['name'] : self::ROOT_PACKAGE;
        self::addPrefixes($prefixes, $composer, $root_name, true);

        $vendor_dir = $composer['config']['vendor-dir'] ?? 'vendor';
        if (!\is_string($vendor_dir) || $vendor_dir === '') {
            $vendor_dir = 'vendor';
        }
        $vendor_path = Paths::isAbsolutePath($vendor_dir) ? $vendor_dir : $root . '/' . $vendor_dir;
        $installed = self::readJson($vendor_path . '/composer/installed.json');
        // (Composer 2 has the list in 'packages', Composer 1 is the list)
        $packages = $installed['packages'] ?? $installed ?? [];
        foreach (\is_array($packages) ? $packages : [] as $package) {
            if (\is_array($package) && \is_string($package['name'] ?? null)) {
                self::addPrefixes($prefixes, $package, $package['name'], false);
            }
        }
        // The longest prefix of a namespace is its package: `Foo\Bar\` before `Foo\`.
        \uksort($prefixes, static function (string $a, string $b): int {
            return \strlen($b) <=> \strlen($a) ?: \strcmp($a, $b);
        });
        return $prefixes;
    }

    /**
     * @param array<string,string> $prefixes
     * @param array<mixed,mixed> $package the contents of a composer.json, or an entry of installed.json
     * @param bool $include_dev whether `autoload-dev` is used as well (it is only installed for the project itself)
     */
    private static function addPrefixes(array &$prefixes, array $package, string $name, bool $include_dev): void
    {
        foreach ($include_dev ? ['autoload', 'autoload-dev'] : ['autoload'] as $section) {
            foreach (['psr-4', 'psr-0'] as $kind) {
                $map = $package[$section][$kind] ?? null;
                if (!\is_array($map)) {
                    continue;
                }
                foreach ($map as $prefix => $_) {
                    $prefix = (string)$prefix;
                    // (Not the empty prefix of a fallback directory, and not a class name prefix of PSR-0 such as `Twig_`)
                    if (!\str_ends_with($prefix, '\\')) {
                        continue;
                    }
                    $prefix = \strtolower(\trim($prefix, '\\'));
                    if ($prefix !== '') {
                        // (The first package of a prefix is kept: the project itself is added first)
                        $prefixes[$prefix . '\\'] ??= $name;
                    }
                }
            }
        }
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
