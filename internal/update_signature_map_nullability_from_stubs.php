#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Makes the parameters of Phan's function signature map nullable where the stubs of php-src declare them as nullable
 * (`?int $length = null`, `array|string|null $options`), so that Phan does not reject a null that PHP accepts.
 *
 * update_signature_map_from_reflection.php does the same from Reflection, but only for the extensions
 * that the running PHP binary was built with. This reads the `*.stub.php` files of php-src, which cover every bundled extension.
 *
 * Usage: php internal/update_signature_map_nullability_from_stubs.php <directory with the *.stub.php files> [--dry-run]
 *
 * Run it with each PHP version in order (8.1, 8.2, ..., 8.6), with the stubs of the php-src branch of that version:
 * the running PHP version selects the map that is checked and the file that is updated (the base map for 8.1, the delta for 8.2+).
 * The stubs of a branch can be exported without checking it out:
 *
 *     git -C php-src archive origin/PHP-8.1 $(git -C php-src ls-tree -r --name-only origin/PHP-8.1 | grep '\.stub\.php$') | tar -x -C stubs81
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */

require_once __DIR__ . '/update_signature_map_from_reflection.php';

use ast\Node;
use Phan\Language\UnionType;

/**
 * Reads the nullability of parameters from php-src stubs and updates the signature map files.
 */
final class SignatureMapStubNullabilityUpdater
{
    /**
     * @return array<string,list<array{name:string,nullable:bool}>> maps the lowercase key of the signature map
     * (`strlen`, `pdo::query`, `random\randomizer::getint`) to the parameters that the stubs declare
     */
    public static function collectStubParameters(string $directory): array
    {
        $result = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.stub.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        foreach ($files as $path) {
            try {
                $root = ast\parse_file($path, max(ast\get_supported_versions()));
            } catch (ParseError $e) {
                fprintf(STDERR, "Skipping %s: %s\n", $path, $e->getMessage());
                continue;
            }
            self::collectFromStatements($root, '', $result);
        }
        ksort($result);
        return $result;
    }

    /**
     * @param array<string,list<array{name:string,nullable:bool}>> $result
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
                        // `namespace Foo;` applies to the statements after it
                        $namespace = $name;
                    }
                    break;
                case ast\AST_FUNC_DECL:
                    self::add($result, self::qualify($namespace, (string)$node->children['name']), $node);
                    break;
                case ast\AST_CLASS:
                    $class_name = self::qualify($namespace, (string)$node->children['name']);
                    foreach ($node->children['stmts']->children ?? [] as $member) {
                        if ($member instanceof Node && $member->kind === ast\AST_METHOD) {
                            self::add($result, $class_name . '::' . $member->children['name'], $member);
                        }
                    }
                    break;
                case ast\AST_IF:
                case ast\AST_IF_ELEM:
                case ast\AST_STMT_LIST:
                    self::collectFromStatements($node->kind === ast\AST_IF_ELEM ? $node->children['stmts'] : $node, $namespace, $result);
                    break;
            }
        }
    }

    private static function qualify(string $namespace, string $name): string
    {
        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * @param array<string,list<array{name:string,nullable:bool}>> $result
     * @param Node $node a function or method declaration
     */
    private static function add(array &$result, string $name, Node $node): void
    {
        $params = [];
        foreach ($node->children['params']->children as $param) {
            if ($param instanceof Node) {
                $params[] = ['name' => (string)$param->children['name'], 'nullable' => self::isNullableTypeNode($param->children['type'])];
            }
        }
        $key = strtolower($name);
        $existing = $result[$key] ?? null;
        if ($existing !== null && array_column($existing, 'name') === array_column($params, 'name')) {
            // Declared more than once (e.g. for different platforms): a parameter is nullable if it is in one of the declarations
            foreach ($params as $i => $param) {
                $params[$i]['nullable'] = $param['nullable'] || $existing[$i]['nullable'];
            }
        }
        $result[$key] = $params;
    }

    /**
     * @return bool whether the type is explicitly nullable: `?T`, or a union type with `null`. Not `mixed`: the map may deliberately be narrower.
     */
    private static function isNullableTypeNode(mixed $type): bool
    {
        if (!($type instanceof Node)) {
            return false;
        }
        if ($type->kind === ast\AST_NULLABLE_TYPE) {
            return true;
        }
        if ($type->kind === ast\AST_TYPE_UNION) {
            foreach ($type->children as $part) {
                if ($part instanceof Node && $part->kind === ast\AST_TYPE && $part->flags === ast\flags\TYPE_NULL) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Computes the entries of the signature map for the running PHP version that should change
     * @param array<string,list<array{name:string,nullable:bool}>> $stub_parameters
     * @return array<string,array{old:array<int|string,string>,new:array<int|string,string>}> maps the lowercase key to the current and the new entry
     */
    public static function computeUpdates(array $stub_parameters): array
    {
        $map = UnionType::internalFunctionSignatureMap(PHP_VERSION_ID);
        $updates = [];
        foreach ($stub_parameters as $key => $params) {
            $entry = $map[$key] ?? null;
            if ($entry === null || isset($map[$key . "'1"]) || isset(SignatureMapReflectionChecker::DELIBERATE_DIFFERENCES[$key])) {
                continue;
            }
            $new = $entry;
            $i = 0;
            foreach ($entry as $spec => $type) {
                if (!is_string($spec)) {
                    continue;
                }
                $param = $params[$i++] ?? null;
                if ($param === null) {
                    break;
                }
                // Only a parameter of the same name at the same position: the map of an extension may differ from the stub in other ways
                if ($param['nullable'] && strcasecmp($param['name'], SignatureMapReflectionChecker::parseParamSpec($spec)['name']) === 0) {
                    $new[$spec] = SignatureMapUpdater::makeNullable($type);
                }
            }
            if ($new !== $entry) {
                $updates[$key] = ['old' => $entry, 'new' => $new];
            }
        }
        return $updates;
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
    fwrite(STDERR, "Usage: php {$argv[0]} <directory with the *.stub.php files of php-src> [--dry-run]\n");
    exit(1);
}
$updates = SignatureMapStubNullabilityUpdater::computeUpdates(SignatureMapStubNullabilityUpdater::collectStubParameters($directory));
foreach ($updates as $key => ['old' => $old, 'new' => $new]) {
    printf("%s\n  old: %s\n  new: %s\n", $key, SignatureMapUpdater::serializeEntry($old), SignatureMapUpdater::serializeEntry($new));
}
printf("%d entries to update for PHP %d.%d\n", count($updates), PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
if (in_array('--dry-run', $argv, true)) {
    exit(0);
}
if (PHP_VERSION_ID < 80200) {
    SignatureMapUpdater::updateBaseMap($updates);
} else {
    SignatureMapUpdater::updateDelta(PHP_MAJOR_VERSION . PHP_MINOR_VERSION, $updates);
}
