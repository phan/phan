<?php

declare(strict_types=1);

namespace Phan\Library;

use Phan\Config;

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

    /** @var list<string> the project root directory, as it is given and with symbolic links resolved (absolute, with a trailing slash) */
    private static $root_dirs = [];

    /** @var list<string> the vendor directory (the same two forms) */
    private static $vendor_dirs = [];

    /** @var array<string,string> maps the directory of an installed package (absolute, with a trailing slash) to its name, longest first */
    private static $package_dirs = [];

    /** @var array<string,string> maps the other directories of `exclude_analysis_directory_list` (absolute, with a trailing slash) to the name of their package, longest first */
    private static $excluded_dirs = [];

    /** @var array<string,string> the package of a file ('' for none), for the path that a FileRef has */
    private static $cache = [];

    /** The package of the files of the project itself */
    private const ROOT_PACKAGE = '(project)';

    /**
     * Forgets what was loaded (when the configuration, the project or its packages change).
     */
    public static function reset(): void
    {
        self::$loaded_root = null;
        self::$root_dirs = [];
        self::$vendor_dirs = [];
        self::$package_dirs = [];
        self::$excluded_dirs = [];
        self::$cache = [];
    }

    /**
     * @param string $file the path of a file, as a FileRef has it
     * @return ?string the package that the file belongs to, or null for a file that is not in the project
     * (such as the stubs of Phan for internal functions, which have the file name `internal`)
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
            if ($file !== '' && $file !== 'internal') {
                // The directory of the file with symbolic links resolved, which is what the directories below are compared with
                // (a package of a Composer path repository is a link to a directory that can be anywhere).
                // The path as it is given says the most if it is in the vendor directory (`vendor/acme/lib` can be a link,
                // and need not be in installed.json). Otherwise the path with symbolic links resolved is used.
                $path = Config::projectPath($file);
                $package = self::packageOfDirectory(self::directoryOf(self::normalize($path)), true);
                if ($package === '') {
                    $real_path = \realpath($path);
                    $package = self::packageOfDirectory(self::directoryOf(self::normalize(\is_string($real_path) ? $real_path : $path)), false);
                }
            }
            self::$cache[$file] = $package;
        }
        return $package === '' ? null : $package;
    }

    /**
     * @return string the directory of the absolute path $path, with a trailing slash
     */
    private static function directoryOf(string $path): string
    {
        $slash = \strrpos($path, '/');
        return $slash === false ? '/' : \substr($path, 0, $slash + 1);
    }

    /**
     * @param string $dir an absolute directory with a trailing slash
     * @param bool $only_installed whether only the installed packages and the vendor directory are looked at
     * @return string the package of its files, or '' if it is not in the project (or, with $only_installed, not installed)
     */
    private static function packageOfDirectory(string $dir, bool $only_installed): string
    {
        // (An installed package can be outside of the vendor directory: `install-path` of composer/installers or of a path repository)
        foreach (self::$package_dirs as $package_dir => $name) {
            if (\str_starts_with($dir, $package_dir)) {
                return $name;
            }
        }
        foreach (self::$vendor_dirs as $vendor_dir) {
            if (\str_starts_with($dir, $vendor_dir)) {
                // Not a package of installed.json: the directory `vendor/name/package`, or what there is of it.
                $parts = \explode('/', \substr($dir, \strlen($vendor_dir)), 3);
                return 'vendor:' . \strtolower($parts[0] . '/' . ($parts[1] ?? ''));
            }
        }
        if ($only_installed) {
            return '';
        }
        foreach (self::$excluded_dirs as $excluded_dir => $id) {
            if (\str_starts_with($dir, $excluded_dir)) {
                return $id;
            }
        }
        foreach (self::$root_dirs as $root_dir) {
            if (\str_starts_with($dir, $root_dir)) {
                return self::ROOT_PACKAGE;
            }
        }
        return '';
    }

    private static function load(string $root): void
    {
        self::reset();
        self::$loaded_root = $root;
        self::$root_dirs = self::directoryForms($root);
        $composer = self::readJson($root . '/composer.json') ?? [];
        $vendor_path = self::vendorPath($root, $composer);
        self::$vendor_dirs = self::directoryForms($vendor_path);

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
            foreach (self::directoryForms($package_path) as $package_dir) {
                // (Not the project itself, which a package can be installed as)
                if (!\in_array($package_dir, self::$root_dirs, true)) {
                    $package_dirs[$package_dir] ??= $name;
                }
            }
        }
        // The longest directory first: a package can be installed in a directory of another one.
        \uksort($package_dirs, static function (string $a, string $b): int {
            return \strlen($b) <=> \strlen($a) ?: \strcmp($a, $b);
        });
        self::$package_dirs = $package_dirs;

        $excluded_dirs = [];
        foreach (Config::getValue('exclude_analysis_directory_list') as $excluded_dir) {
            if (!\is_string($excluded_dir) || $excluded_dir === '') {
                continue;
            }
            $forms = self::directoryForms(Paths::isAbsolutePath($excluded_dir) ? $excluded_dir : $root . '/' . $excluded_dir);
            foreach ($forms as $dir) {
                if (!\in_array($dir, self::$vendor_dirs, true) && !\in_array($dir, self::$root_dirs, true)) {
                    // (One name for the directory as it is given and as its symbolic links resolve)
                    $excluded_dirs[$dir] ??= 'excluded:' . $forms[0];
                }
            }
        }
        \uksort($excluded_dirs, static function (string $a, string $b): int {
            return \strlen($b) <=> \strlen($a) ?: \strcmp($a, $b);
        });
        self::$excluded_dirs = $excluded_dirs;
    }

    /**
     * @return list<string> the absolute directory $path with a trailing slash: as it is given, and with symbolic links resolved if that differs
     */
    private static function directoryForms(string $path): array
    {
        $forms = [self::normalize($path) . '/'];
        $real_path = \realpath($path);
        if (\is_string($real_path)) {
            $real_form = self::normalize($real_path) . '/';
            if ($real_form !== $forms[0]) {
                $forms[] = $real_form;
            }
        }
        return $forms;
    }

    /**
     * @return string $path with forward slashes, without `.`, `..` and repeated slashes, and without a trailing slash
     */
    private static function normalize(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $parts = [];
        foreach (\explode('/', $path) as $part) {
            if ($part === '..') {
                \array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }
        return ($path !== '' && $path[0] === '/' ? '/' : '') . \implode('/', $parts);
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
