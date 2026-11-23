#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lists the functions, methods, constants and class constants that php-src marks as deprecated
 * and that are missing from the tables of Phan\Plugin\Internal\PhpDeprecationPlugin.
 *
 * Phan gets the deprecations of the functions and methods of the running PHP from Reflection,
 * but not for an extension that is not loaded, for a class that Phan's stubs declare,
 * for a target_php_version newer than the running PHP, or for constants. The tables of the plugin cover those.
 *
 * The deprecations are read from
 * - the `#[\Deprecated(since: '8.x', message: '...')]` attributes of the `*.stub.php` files, and
 * - the `REGISTER_PDO_*_CLASS_CONST_*_DEPRECATED_ALIAS_85()` calls of the PDO drivers (the driver constants of the PDO class),
 *   if the directory also has the `ext/pdo*` C sources.
 *
 * Usage: php internal/check_deprecations_against_php_src.php <directory with the files of php-src> [--php]
 *
 * `--php` prints the missing entries as PHP code for the tables of the plugin.
 * Use the newest branch of php-src: its attributes have the version of every deprecation.
 * The files can be exported without checking the branch out, with `git archive origin/PHP-8.6 <files> | tar -x -C <directory>`
 * for the files of `git ls-tree -r --name-only origin/PHP-8.6` that end in `.stub.php` or are C sources in a directory `ext/pdo...`.
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use ast\Node;
use Phan\Plugin\Internal\PhpDeprecationPlugin;

/**
 * Reads the deprecations of php-src and compares them with the tables of PhpDeprecationPlugin.
 */
final class PhpSrcDeprecationChecker
{
    /**
     * @var array<string,true> the kind and name of the elements with a declaration that is not deprecated.
     * A stub may declare a function twice, e.g. ldap_connect() is only deprecated in the build for Oracle LDAP.
     */
    private static $not_deprecated = [];

    /**
     * @return list<array{kind:string,name:string,since:?string,message:?string}> the deprecated elements,
     * with the kind 'function', 'method', 'const' or 'class_const'
     */
    public static function collect(string $directory): array
    {
        $stub_files = [];
        $c_files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!($file instanceof SplFileInfo)) {
                continue;
            }
            if (str_ends_with($file->getFilename(), '.stub.php')) {
                $stub_files[] = $file->getPathname();
            } elseif (preg_match('@/ext/pdo[a-z_]*/[a-z_]+\.c$@', $file->getPathname())) {
                $c_files[] = $file->getPathname();
            }
        }
        sort($stub_files);
        sort($c_files);
        $result = [];
        foreach ($stub_files as $path) {
            try {
                $root = ast\parse_file($path, max(ast\get_supported_versions()));
            } catch (ParseError $e) {
                fprintf(STDERR, "Skipping %s: %s\n", $path, $e->getMessage());
                continue;
            }
            self::collectFromStatements($root, '', $result);
        }
        foreach ($c_files as $path) {
            self::collectPdoDriverConstants((string)file_get_contents($path), $result);
        }
        return array_values(array_filter($result, static fn(array $element): bool => !isset(self::$not_deprecated[$element['kind'] . ' ' . $element['name']])));
    }

    /**
     * @param list<array{kind:string,name:string,since:?string,message:?string}> $result
     */
    private static function collectFromStatements(Node $statements, string $namespace, array &$result): void
    {
        foreach ($statements->children as $node) {
            if (!($node instanceof Node)) {
                continue;
            }
            switch ($node->kind) {
                case ast\AST_NAMESPACE:
                    $name = (string)($node->children['name'] ?? '');
                    if ($node->children['stmts'] instanceof Node) {
                        self::collectFromStatements($node->children['stmts'], $name, $result);
                    } else {
                        $namespace = $name;
                    }
                    break;
                case ast\AST_FUNC_DECL:
                    self::add($result, 'function', self::qualify($namespace, (string)$node->children['name']), $node->children['attributes']);
                    break;
                case ast\AST_CONST_DECL:
                    // The attributes of a global constant are a child of the declaration (php 8.5+)
                    $attributes = null;
                    foreach ($node->children as $child) {
                        if ($child instanceof Node && $child->kind === ast\AST_ATTRIBUTE_LIST) {
                            $attributes = $child;
                        }
                    }
                    foreach ($node->children as $child) {
                        if ($child instanceof Node && $child->kind === ast\AST_CONST_ELEM) {
                            self::add($result, 'const', self::qualify($namespace, (string)$child->children['name']), $attributes);
                        }
                    }
                    break;
                case ast\AST_CLASS:
                    $class_name = self::qualify($namespace, (string)$node->children['name']);
                    foreach ($node->children['stmts']->children ?? [] as $member) {
                        if (!($member instanceof Node)) {
                            continue;
                        }
                        if ($member->kind === ast\AST_METHOD) {
                            self::add($result, 'method', $class_name . '::' . $member->children['name'], $member->children['attributes']);
                        } elseif ($member->kind === ast\AST_CLASS_CONST_GROUP) {
                            foreach ($member->children['const']->children as $const) {
                                if ($const instanceof Node) {
                                    self::add($result, 'class_const', $class_name . '::' . $const->children['name'], $member->children['attributes']);
                                }
                            }
                        }
                    }
                    break;
                case ast\AST_IF:
                case ast\AST_STMT_LIST:
                    self::collectFromStatements($node, $namespace, $result);
                    break;
                case ast\AST_IF_ELEM:
                    self::collectFromStatements($node->children['stmts'], $namespace, $result);
                    break;
            }
        }
    }

    private static function qualify(string $namespace, string $name): string
    {
        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * Adds the element to $result if $attributes has #[\Deprecated]
     * @param list<array{kind:string,name:string,since:?string,message:?string}> $result
     */
    private static function add(array &$result, string $kind, string $name, ?Node $attributes): void
    {
        foreach ($attributes->children ?? [] as $group) {
            foreach ($group->children ?? [] as $attribute) {
                if (!($attribute instanceof Node) || strcasecmp(ltrim((string)($attribute->children['class']->children['name'] ?? ''), '\\'), 'Deprecated') !== 0) {
                    continue;
                }
                $info = ['message' => null, 'since' => null];
                foreach ($attribute->children['args']->children ?? [] as $i => $arg) {
                    if ($arg instanceof Node && $arg->kind === ast\AST_NAMED_ARG) {
                        $info[(string)$arg->children['name']] = is_scalar($arg->children['expr']) ? (string)$arg->children['expr'] : null;
                    } elseif (is_scalar($arg)) {
                        $info[$i === 0 ? 'message' : 'since'] = (string)$arg;
                    }
                }
                $result[] = ['kind' => $kind, 'name' => $name, 'since' => $info['since'], 'message' => $info['message']];
                return;
            }
        }
        self::$not_deprecated["$kind $name"] = true;
    }

    /**
     * The driver constants of the PDO class (PDO::MYSQL_ATTR_INIT_COMMAND, ...) are deprecated since php 8.5 in favor of
     * the constants of the driver classes (Pdo\Mysql::ATTR_INIT_COMMAND). They are registered from C code.
     *
     * @param list<array{kind:string,name:string,since:?string,message:?string}> $result
     */
    private static function collectPdoDriverConstants(string $source, array &$result): void
    {
        // Without a replacement, e.g. REGISTER_PDO_CLASS_CONST_LONG_DEPRECATED_85("PGSQL_TRANSACTION_IDLE", ...)
        preg_match_all('/\bREGISTER_PDO_CLASS_CONST_\w+_DEPRECATED_85\("(\w+)"/', $source, $matches);
        foreach ($matches[1] as $name) {
            $result[] = ['kind' => 'class_const', 'name' => 'PDO::' . $name, 'since' => '8.5', 'message' => null];
        }
        // e.g. REGISTER_PDO_CLASS_CONST_LONG_DEPRECATED_ALIAS_85(base_name, "MYSQL_", "Pdo\\Mysql::", value)
        if (!preg_match('/^#define (REGISTER_PDO_\w+_DEPRECATED_ALIAS_85)\(base_name, value\)\s*\\\\\s*REGISTER_PDO_\w+_DEPRECATED_ALIAS_85\(base_name, "(\w+)", "([^"]+)", value\)/m', $source, $definition)) {
            return;
        }
        [, $macro, $old_prefix, $new_prefix] = $definition;
        $new_prefix = str_replace('\\\\', '\\', $new_prefix);
        preg_match_all('/\b' . preg_quote($macro, '/') . '\("(\w+)"/', $source, $matches);
        foreach ($matches[1] as $name) {
            $result[] = ['kind' => 'class_const', 'name' => 'PDO::' . $old_prefix . $name, 'since' => '8.5', 'message' => "use $new_prefix$name instead"];
        }
    }

    /**
     * @param array{kind:string,name:string,since:?string,message:?string} $element
     * @return int the php version id of the deprecation, e.g. 80400. Elements deprecated before php 8.0 are deprecated in every supported version.
     */
    public static function versionId(array $element): int
    {
        if (preg_match('/^(\d+)\.(\d+)$/', (string)$element['since'], $match)) {
            return max(80000, (int)$match[1] * 10000 + (int)$match[2] * 100);
        }
        return 80000;
    }

    /**
     * @param array{kind:string,name:string,since:?string,message:?string} $element
     * @return string the reason for the tables of the plugin, e.g. `Deprecated in PHP 8.1, as the constant has no effect.`
     */
    public static function reason(array $element): string
    {
        $reason = 'Deprecated in PHP ' . ($element['since'] ?? '8.0');
        $message = trim((string)$element['message']);
        return $reason . ($message !== '' ? ', ' . rtrim($message, '.') : '') . '.';
    }

    /**
     * @param list<array{kind:string,name:string,since:?string,message:?string}> $elements
     * @return array<string,list<array{kind:string,name:string,since:?string,message:?string}>> the elements that the tables of the plugin do not have, by kind
     */
    public static function findMissing(array $elements): array
    {
        $tables = PhpDeprecationPlugin::getDeprecationTables();
        $missing = [];
        foreach ($elements as $element) {
            $name = $element['name'];
            switch ($element['kind']) {
                case 'function':
                    $is_known = isset($tables['functions'][strtolower($name)]);
                    break;
                case 'method':
                    [$class, $method] = explode('::', strtolower($name));
                    $is_known = isset($tables['methods'][$class][$method]);
                    break;
                case 'const':
                    $is_known = isset($tables['global_constants'][$name]);
                    break;
                default:
                    $is_known = isset($tables['class_constants']['\\' . $name]);
                    break;
            }
            if (!$is_known) {
                $missing[$element['kind']][] = $element;
            }
        }
        foreach ($missing as &$list) {
            usort($list, static fn(array $a, array $b): int => [self::versionId($a), $a['name']] <=> [self::versionId($b), $b['name']]);
        }
        ksort($missing);
        return $missing;
    }

    /**
     * Prints the missing entries as PHP code for the tables of the plugin
     * @param array<string,list<array{kind:string,name:string,since:?string,message:?string}>> $missing
     */
    public static function printAsPhp(array $missing): void
    {
        foreach ($missing as $kind => $elements) {
            echo "// $kind\n";
            if ($kind === 'method') {
                $by_class = [];
                foreach ($elements as $element) {
                    [$class, $method] = explode('::', strtolower($element['name']));
                    $by_class[$class][$method] = self::versionId($element);
                }
                ksort($by_class);
                foreach ($by_class as $class => $methods) {
                    ksort($methods);
                    echo "        " . var_export($class, true) . " => [\n";
                    foreach ($methods as $method => $version) {
                        echo "            " . var_export($method, true) . " => $version,\n";
                    }
                    echo "        ],\n";
                }
                continue;
            }
            foreach ($elements as $element) {
                $key = $kind === 'function' ? strtolower($element['name']) : ($kind === 'class_const' ? '\\' . $element['name'] : $element['name']);
                printf(
                    "        %s => [\n            'min_php_version' => %d,\n            'reason' => %s,\n        ],\n",
                    var_export($key, true),
                    self::versionId($element),
                    var_export(self::reason($element), true)
                );
            }
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}
$directory = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $directory = $arg;
    }
}
if (!is_string($directory) || !is_dir($directory)) {
    fwrite(STDERR, "Usage: php {$argv[0]} <directory with the files of php-src> [--php]\n");
    exit(1);
}
$elements = PhpSrcDeprecationChecker::collect($directory);
$missing = PhpSrcDeprecationChecker::findMissing($elements);
if (in_array('--php', $argv, true)) {
    PhpSrcDeprecationChecker::printAsPhp($missing);
    exit(0);
}
printf("%d deprecated elements in php-src\n", count($elements));
foreach ($missing as $kind => $list) {
    printf("%-12s %d missing\n", $kind, count($list));
    foreach ($list as $element) {
        printf("  %s (%s)\n", $element['name'], $element['since'] ?? '?');
    }
}
exit($missing ? 1 : 0);
