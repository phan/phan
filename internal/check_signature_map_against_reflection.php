#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compares Phan's function signature map for the running PHP version (the base map with the deltas
 * up to that version applied) with Reflection, for the functions and methods of the extensions bundled with php-src.
 *
 * Reports the differences that cause false positives or false negatives in Phan:
 * - arity: the number of required or optional parameters, or whether the last parameter is variadic
 * - name: parameter names (used for named arguments)
 * - by-ref: whether a parameter is passed by reference
 * - optional: whether a parameter is optional or variadic
 * - nullable: parameters that are nullable for Reflection (`?int`) and not in the map, so that Phan rejects a null that PHP accepts
 * - missing: functions and methods that Reflection knows but the map does not
 *
 * Usage: php internal/check_signature_map_against_reflection.php [--json=out.json]
 * Run it with each supported PHP version; the result describes the map for that version.
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Phan\Language\UnionType;

/**
 * Checks Phan's signature map against Reflection.
 */
final class SignatureMapReflectionChecker
{
    /**
     * The extensions bundled with php-src (lowercase Reflection extension names), including the ones removed since PHP 8.1.
     * Other extensions (PECL, or php-src's own test extensions) are versioned independently of PHP, so they are not checked.
     */
    private const BUNDLED_EXTENSIONS = [
        'bcmath', 'bz2', 'calendar', 'com_dotnet', 'core', 'ctype', 'curl', 'date', 'dba', 'dom', 'enchant', 'exif', 'ffi',
        'fileinfo', 'filter', 'ftp', 'gd', 'gettext', 'gmp', 'hash', 'iconv', 'imap', 'intl', 'json', 'ldap', 'lexbor', 'libxml',
        'mbstring', 'mysqli', 'mysqlnd', 'oci8', 'odbc', 'openssl', 'pcntl', 'pcre', 'pdo', 'pdo_dblib', 'pdo_firebird',
        'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phar', 'posix', 'pspell', 'random', 'readline',
        'reflection', 'session', 'shmop', 'simplexml', 'snmp', 'soap', 'sockets', 'sodium', 'spl', 'sqlite3', 'standard',
        'sysvmsg', 'sysvsem', 'sysvshm', 'tidy', 'tokenizer', 'uri', 'xml', 'xmlreader', 'xmlwriter', 'xsl', 'zend opcache',
        'zip', 'zlib',
    ];

    /**
     * Entries that Phan models differently from Reflection on purpose. The checker skips them and the updater does not change them.
     */
    public const DELIBERATE_DIFFERENCES = [
        // Phan models the interleaved array and flag arguments.
        'array_multisort' => true,
        // __invoke() of a closure accepts the closure's parameters, Reflection reports none.
        'closure::__invoke' => true,
        // The extra parameters are only available with Oracle LDAP (build dependent).
        'ldap_connect' => true,
        // Reflection says `mixed &...$vars`, but at least one variable is required (`O+`, or as many as the non-empty $types).
        'mysqli_stmt::bind_param' => true,
        'mysqli_stmt::bind_result' => true,
        'mysqli_stmt_bind_param' => true,
        'mysqli_stmt_bind_result' => true,
    ];

    /**
     * Parse a parameter spec of Phan's signature map, e.g. `'&w_matches='` or `'...values'`
     * @return array{name:string,optional:bool,by_ref:bool,variadic:bool}
     */
    public static function parseParamSpec(string $spec): array
    {
        $by_ref = false;
        $variadic = false;
        $optional = false;
        if (str_starts_with($spec, '&')) {
            $by_ref = true;
            $spec = (string)preg_replace('/^&(rw_|w_|r_)?/', '', $spec);
        }
        if (str_starts_with($spec, '...')) {
            $variadic = true;
            $spec = substr($spec, 3);
            if ($by_ref) {
                // e.g. `&...w_vars`
                $spec = (string)preg_replace('/^(rw_|w_|r_)/', '', $spec);
            }
        }
        if (str_ends_with($spec, '=')) {
            $optional = true;
            $spec = substr($spec, 0, -1);
        }
        return ['name' => $spec, 'optional' => $optional || $variadic, 'by_ref' => $by_ref, 'variadic' => $variadic];
    }

    /**
     * Describes a parameter from Reflection in the same format as parseParamSpec()
     * @return array{name:string,optional:bool,by_ref:bool,variadic:bool}
     */
    public static function describeReflectionParameter(ReflectionParameter $parameter): array
    {
        return [
            'name' => $parameter->getName(),
            'optional' => $parameter->isOptional(),
            'by_ref' => $parameter->isPassedByReference(),
            'variadic' => $parameter->isVariadic(),
        ];
    }

    /**
     * Returns true if the extension is bundled with php-src
     */
    public static function isBundledExtension(string|false|null $extension_name): bool
    {
        if (!is_string($extension_name) || $extension_name === '') {
            return false;
        }
        return in_array(strtolower($extension_name), self::BUNDLED_EXTENSIONS, true);
    }

    /**
     * Returns the parameters of an entry of Phan's signature map
     * @param array<int|string,string> $entry
     * @return list<array{name:string,optional:bool,by_ref:bool,variadic:bool}>
     */
    public static function paramsOfEntry(array $entry): array
    {
        $params = [];
        foreach ($entry as $key => $_) {
            if (is_string($key)) {
                $params[] = self::parseParamSpec($key);
            }
        }
        return $params;
    }

    /**
     * @param list<array{name:string,optional:bool,by_ref:bool,variadic:bool}> $map_params
     * @param list<array{name:string,optional:bool,by_ref:bool,variadic:bool}> $reflection_params
     * @return list<string> the kinds of differences
     */
    public static function compare(array $map_params, array $reflection_params): array
    {
        $kinds = [];
        /**
         * @param list<array{name:string,optional:bool,by_ref:bool,variadic:bool}> $params
         * @return array{0:int,1:int,2:bool} the number of required parameters, the number of parameters, and whether the last one is variadic
         */
        $count = static fn(array $params): array => [
            count(array_filter($params, /** @param array{name:string,optional:bool,by_ref:bool,variadic:bool} $p */ static fn(array $p): bool => !$p['optional'])),
            count($params),
            $params && end($params)['variadic'],
        ];
        if ($count($map_params) !== $count($reflection_params)) {
            $kinds[] = 'arity';
        }
        $n = min(count($map_params), count($reflection_params));
        for ($i = 0; $i < $n; $i++) {
            // Named arguments are case-sensitive
            if ($map_params[$i]['name'] !== $reflection_params[$i]['name']) {
                $kinds[] = 'name';
                break;
            }
        }
        for ($i = 0; $i < $n; $i++) {
            if ($map_params[$i]['by_ref'] !== $reflection_params[$i]['by_ref']) {
                $kinds[] = 'by-ref';
                break;
            }
        }
        // The same counts can hide swapped flags (e.g. `string, offset=, length` instead of `string, offset, length=`)
        for ($i = 0; $i < $n; $i++) {
            if ($map_params[$i]['optional'] !== $reflection_params[$i]['optional'] || $map_params[$i]['variadic'] !== $reflection_params[$i]['variadic']) {
                $kinds[] = 'optional';
                break;
            }
        }
        return $kinds;
    }

    /**
     * @return array<string,ReflectionFunctionAbstract> lowercase map key => reflection
     */
    public static function collectReflection(): array
    {
        $result = [];
        foreach (get_defined_functions()['internal'] as $function_name) {
            $function = new ReflectionFunction($function_name);
            if (!self::isBundledExtension($function->getExtensionName())) {
                continue;
            }
            $result[strtolower($function_name)] = $function;
        }
        foreach (array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $class_name) {
            $class = new ReflectionClass($class_name);
            if (!$class->isInternal() || !self::isBundledExtension($class->getExtensionName())) {
                continue;
            }
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                $result[strtolower($class->getName() . '::' . $method->getName())] = $method;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Compares the signature map for the running PHP version with Reflection
     * @return array{php_version:string,differences:array<string,array{kinds:list<string>,map:array<int|string,string>,reflection:array<int|string,string>}>,missing:array<string,array<int|string,string>>,counts:array<string,int>}
     */
    public static function run(): array
    {
        $map = UnionType::internalFunctionSignatureMap(PHP_VERSION_ID);
        $report = [
            'php_version' => PHP_VERSION,
            'differences' => [],
            'missing' => [],
            'counts' => ['checked' => 0, 'arity' => 0, 'name' => 0, 'by-ref' => 0, 'optional' => 0, 'nullable' => 0, 'missing' => 0, 'skipped_alternates' => 0],
        ];
        foreach (self::collectReflection() as $key => $reflection) {
            $entry = $map[$key] ?? null;
            if ($entry === null) {
                $report['missing'][$key] = self::reflectionSpec($reflection);
                $report['counts']['missing']++;
                continue;
            }
            if (isset($map[$key . "'1"]) || isset(self::DELIBERATE_DIFFERENCES[$key])) {
                // Phan models this function with several alternative signatures (e.g. implode()), or differently on purpose.
                $report['counts']['skipped_alternates']++;
                continue;
            }
            $report['counts']['checked']++;
            $reflection_params = array_map([self::class, 'describeReflectionParameter'], $reflection->getParameters());
            $kinds = self::compare(self::paramsOfEntry($entry), $reflection_params);
            if (self::hasNullabilityDifference($entry, $reflection)) {
                $kinds[] = 'nullable';
            }
            if (!$kinds) {
                continue;
            }
            foreach ($kinds as $kind) {
                $report['counts'][$kind]++;
            }
            $report['differences'][$key] = [
                'kinds' => $kinds,
                'map' => $entry,
                'reflection' => self::reflectionSpec($reflection),
            ];
        }
        return $report;
    }

    /**
     * @param string $type a type from typeToString()
     * @return bool whether Reflection's type is explicitly nullable. `mixed` is not: the map may deliberately be narrower.
     */
    public static function isNullableReflectionType(string $type): bool
    {
        return str_starts_with($type, '?') || preg_match('/(^|\|)null(\||$)/D', $type) === 1;
    }

    /**
     * @param string $type a type of Phan's signature map
     * @return bool whether the type accepts null (an empty type and `mixed` accept anything)
     */
    public static function isNullableMapType(string $type): bool
    {
        if ($type === '') {
            return true;
        }
        // Only the top level counts: `array<string,int|null>` does not accept null
        $depth = 0;
        $part = '';
        $parts = [];
        foreach (str_split($type) as $char) {
            if ($char === '|' && $depth === 0) {
                $parts[] = $part;
                $part = '';
                continue;
            }
            if ($char === '<' || $char === '{' || $char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === '>' || $char === '}' || $char === ')' || $char === ']') {
                $depth--;
            }
            $part .= $char;
        }
        $parts[] = $part;
        foreach ($parts as $part) {
            if ($part === 'null' || $part === 'mixed' || str_starts_with($part, '?')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int|string,string> $entry the entry of Phan's signature map
     * @return bool whether a parameter is nullable for Reflection and not in the map (compared by position)
     */
    public static function hasNullabilityDifference(array $entry, ReflectionFunctionAbstract $reflection): bool
    {
        $reflection_params = $reflection->getParameters();
        $i = 0;
        foreach ($entry as $spec => $type) {
            if (!is_string($spec)) {
                continue;
            }
            $reflection_param = $reflection_params[$i++] ?? null;
            if (!$reflection_param) {
                break;
            }
            if (self::isNullableReflectionType(self::typeToString($reflection_param->getType())) && !self::isNullableMapType($type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int|string,string> the signature of $reflection in the format of Phan's signature map
     */
    public static function reflectionSpec(ReflectionFunctionAbstract $reflection): array
    {
        $spec = [self::typeToString($reflection->hasTentativeReturnType() ? $reflection->getTentativeReturnType() : $reflection->getReturnType())];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            if ($parameter->isVariadic()) {
                $name = "...$name";
            } elseif ($parameter->isOptional()) {
                $name .= '=';
            }
            if ($parameter->isPassedByReference()) {
                $name = "&$name";
            }
            $spec[$name] = self::typeToString($parameter->getType());
        }
        return $spec;
    }

    /**
     * Converts a type from Reflection to the format of Phan's signature map
     */
    public static function typeToString(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            // Class names are written without a leading backslash, as in the rest of the signature map
            $result = $name;
            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                return '?' . $result;
            }
            return $result;
        }
        if ($type instanceof ReflectionUnionType) {
            $parts = array_map(static fn(ReflectionType $t): string => $t instanceof ReflectionIntersectionType ? '(' . self::typeToString($t) . ')' : self::typeToString($t), $type->getTypes());
            return implode('|', $parts);
        }
        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map([self::class, 'typeToString'], $type->getTypes()));
        }
        return (string)$type;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    // Included as a library (e.g. by update_signature_map_from_reflection.php)
    return;
}
$options = getopt('', ['json:']);
$report = SignatureMapReflectionChecker::run();
foreach ($report['counts'] as $kind => $n) {
    printf("%-20s %d\n", $kind, $n);
}
if (isset($options['json']) && is_string($options['json'])) {
    file_put_contents($options['json'], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
} else {
    foreach ($report['differences'] as $key => ['kinds' => $kinds, 'map' => $map, 'reflection' => $reflection]) {
        printf("%s [%s]\n  phan:       %s\n  reflection: %s\n", $key, implode(',', $kinds), (string)json_encode($map, JSON_UNESCAPED_SLASHES), (string)json_encode($reflection, JSON_UNESCAPED_SLASHES));
    }
}
