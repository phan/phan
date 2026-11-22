#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Updates Phan's function signature map so that the parameters of the functions and methods
 * of the extensions bundled with php-src match Reflection for the running PHP version.
 *
 * - With PHP 8.1, the base map (FunctionSignatureMap.php) is updated.
 * - With PHP 8.2+, the delta for that version (FunctionSignatureMap_phpXY_delta.php) is updated:
 *   an entry the delta already adds or changes is replaced, other functions get a new 'changed' entry.
 *
 * The parameter names, order, optionality, by-reference and variadic flags come from Reflection.
 * The phpdoc types and the return type of the existing entry are kept (Reflection's types are usually less precise);
 * Reflection's type is only used for parameters the map does not have.
 *
 * Run it with each PHP version in order (8.1, 8.2, ..., 8.6), since each version's map is based on the previous one.
 *
 * Usage: php internal/update_signature_map_from_reflection.php [--dry-run]
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */

require_once __DIR__ . '/check_signature_map_against_reflection.php';

use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Updates the signature map files.
 */
final class SignatureMapUpdater
{
    /**
     * Entries that Phan models differently from Reflection on purpose.
     */
    private const EXCLUDED = [
        // Phan models the interleaved array and flag arguments.
        'array_multisort' => true,
        // __invoke() of a closure accepts the closure's parameters, Reflection reports none.
        'closure::__invoke' => true,
        // The extra parameters are only available with Oracle LDAP (build dependent).
        'ldap_connect' => true,
    ];

    private const INTERNAL_DIR = __DIR__ . '/../src/Phan/Language/Internal';

    /**
     * @param array<int|string,string> $old the entry of Phan's map
     * @param array<int|string,string> $reflection_spec the entry built from Reflection
     * @return array<int|string,string> the merged entry
     */
    public static function merge(array $old, array $reflection_spec): array
    {
        $old_params = [];
        foreach ($old as $spec => $type) {
            if (is_string($spec)) {
                $old_params[] = ['parsed' => SignatureMapReflectionChecker::parseParamSpec($spec), 'spec' => $spec, 'type' => $type];
            }
        }
        $reflection_names = [];
        foreach ($reflection_spec as $spec => $_) {
            if (is_string($spec)) {
                $reflection_names[] = strtolower(SignatureMapReflectionChecker::parseParamSpec($spec)['name']);
            }
        }
        $result = [$old[0] ?? $reflection_spec[0]];
        $i = 0;
        foreach ($reflection_spec as $spec => $reflection_type) {
            if (!is_string($spec)) {
                continue;
            }
            $parsed = SignatureMapReflectionChecker::parseParamSpec($spec);
            $match = null;
            // Match the old parameter by name, or by position if the old parameter at that position was renamed.
            foreach ($old_params as $old_param) {
                if (strcasecmp($old_param['parsed']['name'], $parsed['name']) === 0) {
                    $match = $old_param;
                    break;
                }
            }
            if ($match === null && isset($old_params[$i]) && !in_array(strtolower($old_params[$i]['parsed']['name']), $reflection_names, true)) {
                $match = $old_params[$i];
            }
            $name = $parsed['name'];
            if ($parsed['by_ref'] && $match !== null && $match['parsed']['by_ref'] && preg_match('/^&(?:\.\.\.)?(rw_|w_|r_)/', $match['spec'], $m)) {
                // Keep the annotation of how the reference is used (read, written, or both)
                $name = $m[1] . $name;
            }
            if ($parsed['variadic']) {
                // Phan's convention for variadic parameters, which are always optional
                $name = '...' . $name . '=';
            } elseif ($parsed['optional']) {
                $name .= '=';
            }
            if ($parsed['by_ref']) {
                $name = '&' . $name;
            }
            $type = $match !== null ? $match['type'] : $reflection_type;
            if ($parsed['variadic'] && $match !== null) {
                // A variadic parameter may replace several old parameters (e.g. fetchAll($mode, $args, $ctor_args) became fetchAll($mode, ...$args)):
                // accept the types of all of them.
                $types = explode('|', $type);
                for ($j = $i + 1; $j < count($old_params); $j++) {
                    if (!in_array(strtolower($old_params[$j]['parsed']['name']), $reflection_names, true)) {
                        $types = array_merge($types, explode('|', $old_params[$j]['type']));
                    }
                }
                $type = implode('|', array_unique($types));
            }
            $result[$name] = $type;
            $i++;
        }
        return $result;
    }

    /**
     * Quote a string like the signature map files do (`'ldap\result'`, not `'ldap\\result'`)
     */
    public static function quote(string $value): string
    {
        if (str_contains($value, "'") || str_ends_with($value, '\\')) {
            return var_export($value, true);
        }
        return "'" . $value . "'";
    }

    /**
     * Returns the return type from Reflection if the return type in Phan's map contradicts it
     * (e.g. `void` for a method returning `true`, or a type that Reflection's type cannot contain), null otherwise.
     * A map type that is more precise than Reflection's (e.g. `static`, templates, `list<string>`) is kept.
     */
    public static function correctedReturnType(string $map_type, ReflectionFunctionAbstract $reflection, \Phan\CodeBase $code_base): ?string
    {
        $reflection_type = $reflection->hasTentativeReturnType() ? $reflection->getTentativeReturnType() : $reflection->getReturnType();
        if (!$reflection_type) {
            return null;
        }
        $reflection_string = SignatureMapReflectionChecker::typeToString($reflection_type);
        if (in_array($reflection_string, ['mixed', 'static'], true) || $map_type === '' || $map_type === 'mixed' || preg_match('/\\bstatic\\b/i', $map_type)) {
            return null;
        }
        try {
            $map_union_type = UnionType::fromStringInContext($map_type, new Context(), Type::FROM_PHPDOC);
            $reflection_union_type = UnionType::fromStringInContext($reflection_string, new Context(), Type::FROM_TYPE);
        } catch (Throwable) {
            return null;
        }
        if ($map_union_type->hasTemplateTypeRecursive()) {
            return null;
        }
        if ($map_union_type->isVoidType() !== $reflection_union_type->isVoidType()) {
            return $reflection_string;
        }
        if ($map_union_type->isVoidType()) {
            return null;
        }
        if (!$map_union_type->asNonLiteralType()->canCastToUnionTypeWithoutConfig($reflection_union_type, $code_base)) {
            return $reflection_string;
        }
        return null;
    }

    /**
     * Serializes an entry in the format of the signature map files
     * @param array<int|string,string> $entry
     */
    public static function serializeEntry(array $entry): string
    {
        $parts = [];
        foreach ($entry as $key => $value) {
            $parts[] = is_string($key) ? self::quote($key) . '=>' . self::quote($value) : self::quote($value);
        }
        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * Computes the entries of the signature map for the running PHP version that should change
     * @return array<string,array{old:array<int|string,string>,new:array<int|string,string>}> maps the lowercase key to the current and the new entry
     */
    public static function computeUpdates(): array
    {
        $map = UnionType::internalFunctionSignatureMap(PHP_VERSION_ID);
        $code_base = require __DIR__ . '/../src/codebase.php';
        $updates = [];
        foreach (SignatureMapReflectionChecker::collectReflection() as $key => $reflection) {
            $entry = $map[$key] ?? null;
            if ($entry === null || isset($map[$key . "'1"]) || isset(self::EXCLUDED[$key])) {
                continue;
            }
            $reflection_params = array_map([SignatureMapReflectionChecker::class, 'describeReflectionParameter'], $reflection->getParameters());
            $new = $entry;
            if (SignatureMapReflectionChecker::compare(SignatureMapReflectionChecker::paramsOfEntry($entry), $reflection_params)) {
                $new = self::merge($entry, SignatureMapReflectionChecker::reflectionSpec($reflection));
            }
            $corrected_return_type = self::correctedReturnType((string)($entry[0] ?? ''), $reflection, $code_base);
            if ($corrected_return_type !== null) {
                $new[0] = $corrected_return_type;
            }
            if ($new !== $entry) {
                $updates[$key] = ['old' => $entry, 'new' => $new];
            }
        }
        return $updates;
    }

    /**
     * Updates the base signature map (PHP 8.1)
     * @param array<string,array{old:array<int|string,string>,new:array<int|string,string>}> $updates
     */
    public static function updateBaseMap(array $updates): void
    {
        $path = self::INTERNAL_DIR . '/FunctionSignatureMap.php';
        $lines = file($path);
        if ($lines === false) {
            throw new RuntimeException("Could not read $path");
        }
        $done = [];
        foreach ($lines as $i => $line) {
            if (!preg_match("/^'([^']+)' => \[/", $line, $m)) {
                continue;
            }
            $key = strtolower($m[1]);
            if (isset($updates[$key])) {
                $lines[$i] = self::quote($m[1]) . ' => ' . self::serializeEntry($updates[$key]['new']) . ",\n";
                $done[$key] = true;
            }
        }
        foreach (array_diff_key($updates, $done) as $key => $_) {
            fwrite(STDERR, "Could not find $key in FunctionSignatureMap.php\n");
        }
        file_put_contents($path, implode('', $lines));
    }

    /**
     * Updates the delta of the signature map for the running PHP version
     * @param array<string,array{old:array<int|string,string>,new:array<int|string,string>}> $updates
     */
    public static function updateDelta(string $version_suffix, array $updates): void
    {
        $path = self::INTERNAL_DIR . "/FunctionSignatureMap_php{$version_suffix}_delta.php";
        $delta = require $path;
        $lines = file($path);
        if ($lines === false) {
            throw new RuntimeException("Could not read $path");
        }

        // 1. Entries that the delta adds: replace their (single-line) entries.
        $added_keys = [];
        foreach ($delta['added'] as $key => $_) {
            $added_keys[strtolower($key)] = $key;
        }
        $section = null;
        foreach ($lines as $i => $line) {
            if (preg_match("/^  '(added|changed|removed)' => \\[/", $line, $m)) {
                $section = $m[1];
                continue;
            }
            if ($section !== 'added' || !preg_match("/^(\\s+)'([^']+)' => \\[/", $line, $m)) {
                continue;
            }
            $key = strtolower($m[2]);
            if (isset($updates[$key])) {
                $lines[$i] = $m[1] . self::quote($m[2]) . ' => ' . self::serializeEntry($updates[$key]['new']) . ",\n";
                unset($updates[$key]);
            }
        }

        // 2. Everything else goes into the 'changed' section, which is regenerated (sorted).
        $changed = [];
        foreach ($delta['changed'] as $key => $entry) {
            $changed[strtolower($key)] = ['key' => $key, 'old' => $entry['old'], 'new' => $entry['new']];
        }
        foreach ($updates as $key => ['old' => $old, 'new' => $new]) {
            if (isset($added_keys[$key])) {
                continue;
            }
            if (isset($changed[$key])) {
                $changed[$key]['new'] = $new;
            } else {
                $changed[$key] = ['key' => $key, 'old' => $old, 'new' => $new];
            }
        }
        // The 'old' signatures must match the map of the previous PHP version, which may have been updated.
        $previous_map = UnionType::internalFunctionSignatureMap(PHP_VERSION_ID - 100);
        foreach ($changed as $key => $entry) {
            if (isset($previous_map[$key])) {
                $changed[$key]['old'] = $previous_map[$key];
            }
            if ($changed[$key]['old'] === $entry['new']) {
                // No longer a change
                unset($changed[$key]);
            }
        }
        ksort($changed);

        $start = null;
        foreach ($lines as $i => $line) {
            if (preg_match("/^  'changed' => \\[/", $line)) {
                $start = $i + 1;
                break;
            }
        }
        if ($start === null) {
            throw new RuntimeException("No 'changed' section in $path");
        }
        $end = $start;
        while (!preg_match('/^  \\],/', $lines[$end])) {
            $end++;
        }
        // Keep the indentation used by the file
        $key_indent = '    ';
        $inner_indent = '      ';
        for ($i = $start; $i < $end; $i++) {
            if (preg_match("/^(\\s+)'old' =>/", $lines[$i], $m)) {
                $inner_indent = $m[1];
            } elseif (!preg_match("/^\\s+'new' =>/", $lines[$i]) && preg_match("/^(\\s+)'[^']+' => \\[/", $lines[$i], $m)) {
                $key_indent = $m[1];
            }
        }
        $section_lines = [];
        foreach ($changed as ['key' => $key, 'old' => $old, 'new' => $new]) {
            $section_lines[] = $key_indent . self::quote($key) . " => [\n";
            $section_lines[] = $inner_indent . "'old' => " . self::serializeEntry($old) . ",\n";
            $section_lines[] = $inner_indent . "'new' => " . self::serializeEntry($new) . ",\n";
            $section_lines[] = $key_indent . "],\n";
        }
        array_splice($lines, $start, $end - $start, $section_lines);
        file_put_contents($path, implode('', $lines));
    }
}

$dry_run = in_array('--dry-run', $argv, true);
$updates = SignatureMapUpdater::computeUpdates();
foreach ($updates as $key => ['old' => $old, 'new' => $new]) {
    printf("%s\n  old: %s\n  new: %s\n", $key, SignatureMapUpdater::serializeEntry($old), SignatureMapUpdater::serializeEntry($new));
}
printf("%d entries to update for PHP %d.%d\n", count($updates), PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
if ($dry_run) {
    exit(0);
}
if (PHP_VERSION_ID < 80200) {
    SignatureMapUpdater::updateBaseMap($updates);
} else {
    SignatureMapUpdater::updateDelta(PHP_MAJOR_VERSION . PHP_MINOR_VERSION, $updates);
}
