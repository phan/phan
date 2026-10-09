#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Pick a deterministic, self-consistent subset of a project's first-party files
 * for benchmarking Phan.
 *
 * Usage:
 *   make_subset.php --root DIR --universe FILE [--fixed-prefix P]... --rate 0.12
 *                   --salt S [--closure none|hard|hard+soft1] [--manifest out.json]
 *                   [--analyze-list subset.analyze.files] > subset.files
 *
 * --universe   list of files Phan parses (e.g. from --dump-parsed-file-list), one per
 *              line, relative to --root (absolute paths under --root are accepted)
 * --fixed-prefix  files with this prefix are always parsed anyway (directory_list,
 *              e.g. vendor/); they are indexed for name resolution but never seeded
 *              and never written to the output
 * --rate       fraction of first-party files used as seeds:
 *              hexdec(substr(sha1(salt . path), 0, 8)) % 1000000 < rate * 1000000
 * --closure    none:       seeds only
 *              hard:       + files declaring classes the selection extends/implements/
 *                          uses as traits, transitively
 *              hard+soft1: hard, then one level of soft references (new X, X::,
 *                          instanceof X, catch (X), param/return/property types) from
 *                          the hard-closed set, then hard closure of what that added
 *
 * --analyze-list  also write seeds + hard closure (the files worth analyzing) to this
 *              path, for Phan's --include-analysis-file-list. stdout keeps the full parse
 *              selection: files added only by the soft level are parsed so that
 *              references resolve, but analyzing them would mostly report their own
 *              unclosed references as undeclared.
 *
 * Names are resolved best-effort (namespace, use imports, leading \); references that
 * do not resolve to a declared class are ignored. When a class is declared both in a
 * fixed-prefix file and a first-party file, nothing is added; among several first-party
 * declarations the lexicographically smallest path wins.
 *
 * The output (stdout) is the sorted selection, one path per line.
 */

const NAME_TOKENS = [T_STRING => true, T_NAME_QUALIFIED => true, T_NAME_FULLY_QUALIFIED => true, T_NAME_RELATIVE => true];
const MODIFIER_TOKENS = [
    T_PUBLIC => true, T_PROTECTED => true, T_PRIVATE => true, T_VAR => true,
    T_STATIC => true, T_READONLY => true, T_ABSTRACT => true, T_FINAL => true,
];
const NOT_CLASS_NAMES = [
    'self' => true, 'static' => true, 'parent' => true, 'int' => true, 'float' => true,
    'string' => true, 'bool' => true, 'array' => true, 'callable' => true, 'iterable' => true,
    'object' => true, 'mixed' => true, 'void' => true, 'never' => true, 'null' => true,
    'false' => true, 'true' => true,
];

function usage(int $code): never
{
    fwrite($code ? STDERR : STDOUT, <<<'EOT'
Usage: make_subset.php --root DIR --universe FILE [--fixed-prefix P]... --rate R --salt S
                       [--closure none|hard|hard+soft1] [--manifest out.json]
                       [--analyze-list subset.analyze.files] > subset.files

EOT);
    exit($code);
}

final class Subset
{
    /** @var array<string,int> lowercase FQCN => id */
    public array $nameIds = [];
    /** @var list<string> id => FQCN as first seen */
    public array $names = [];
    /** @var array<int,list<int>> name id => declaring file ids */
    public array $declFiles = [];
    /** @var array<int,list<int>> file id => hard ref name ids */
    public array $hard = [];
    /** @var array<int,list<int>> file id => soft ref name ids */
    public array $soft = [];
    public int $declCount = 0;
    public int $refCount = 0;

    public function nameId(string $fqcn): int
    {
        $lower = strtolower($fqcn);
        $id = $this->nameIds[$lower] ?? null;
        if ($id === null) {
            $id = count($this->names);
            $this->nameIds[$lower] = $id;
            $this->names[] = $fqcn;
        }
        return $id;
    }
}

/**
 * Resolve a class name token in the current namespace / import context.
 *
 * @param array<string,string> $uses lowercase alias => FQCN
 */
function resolve_name(string $name, int $type, string $ns, array $uses): ?string
{
    switch ($type) {
        case T_NAME_FULLY_QUALIFIED:
            return substr($name, 1);
        case T_NAME_RELATIVE:
            $rest = substr($name, 10); // "namespace\"
            return $ns !== '' ? "$ns\\$rest" : $rest;
        case T_STRING:
            $lower = strtolower($name);
            if (isset(NOT_CLASS_NAMES[$lower])) {
                return null;
            }
            if (isset($uses[$lower])) {
                return $uses[$lower];
            }
            return $ns !== '' ? "$ns\\$name" : $name;
        case T_NAME_QUALIFIED:
            $pos = strpos($name, '\\');
            $first = strtolower(substr($name, 0, (int)$pos));
            if (isset($uses[$first])) {
                return $uses[$first] . substr($name, (int)$pos);
            }
            return $ns !== '' ? "$ns\\$name" : $name;
    }
    return null;
}

/**
 * Scan one file. Returns [declared FQCNs, hard ref FQCNs, soft ref FQCNs].
 *
 * @return array{0:list<string>,1:array<string,true>,2:array<string,true>}
 */
function scan_file(string $code, bool $declOnly): array
{
    // Significant tokens only.
    $ty = [];
    $tx = [];
    foreach (token_get_all($code) as $t) {
        if (is_array($t)) {
            $id = $t[0];
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT
                || $id === T_INLINE_HTML || $id === T_OPEN_TAG || $id === T_CLOSE_TAG) {
                continue;
            }
            $ty[] = $id;
            $tx[] = $t[1];
        } else {
            $ty[] = $t;
            $tx[] = $t;
        }
    }
    $n = count($ty);

    $decl = [];
    $hard = [];
    $soft = [];
    $ns = '';
    $uses = [];
    $stack = [];          // 'ns' | 'class' | 'other'
    $classDepth = 0;      // number of 'class' entries on the stack
    $otherDepth = 0;      // number of 'other' entries on the stack
    $pending = null;      // what the next '{' opens

    $addSoft = static function (int $i) use (&$soft, $ty, $tx, &$ns, &$uses): void {
        $r = resolve_name($tx[$i], $ty[$i], $ns, $uses);
        if ($r !== null && $r !== '') {
            $soft[$r] = true;
        }
    };

    for ($i = 0; $i < $n; $i++) {
        $t = $ty[$i];
        switch ($t) {
            case '{':
            case T_CURLY_OPEN:
            case T_DOLLAR_OPEN_CURLY_BRACES:
                $kind = $t === '{' && $pending !== null ? $pending : 'other';
                $pending = null;
                $stack[] = $kind;
                if ($kind === 'class') {
                    $classDepth++;
                } elseif ($kind === 'other') {
                    $otherDepth++;
                }
                break;
            case '}':
                $kind = array_pop($stack);
                if ($kind === 'class') {
                    $classDepth--;
                } elseif ($kind === 'other') {
                    $otherDepth--;
                }
                break;
            case T_NAMESPACE:
                $next = $ty[$i + 1] ?? null;
                if ($next === T_STRING || $next === T_NAME_QUALIFIED) {
                    $ns = $tx[$i + 1];
                    $uses = [];
                    $i++;
                    if (($ty[$i + 1] ?? null) === '{') {
                        $pending = 'ns';
                    }
                } elseif ($next === '{') {
                    $ns = '';
                    $uses = [];
                    $pending = 'ns';
                }
                break;
            case T_CLASS:
            case T_INTERFACE:
            case T_TRAIT:
            case T_ENUM:
                $prev = $ty[$i - 1] ?? null;
                if ($prev === T_DOUBLE_COLON) {
                    break; // Foo::class
                }
                $pending = 'class';
                if (($ty[$i + 1] ?? null) === T_STRING && $prev !== T_NEW) {
                    $decl[] = $ns !== '' ? $ns . '\\' . $tx[$i + 1] : $tx[$i + 1];
                    $i++;
                }
                break;
            case T_EXTENDS:
            case T_IMPLEMENTS:
                if ($declOnly) {
                    break;
                }
                for ($j = $i + 1; $j < $n; $j++) {
                    $tj = $ty[$j];
                    if (isset(NAME_TOKENS[$tj])) {
                        $r = resolve_name($tx[$j], $tj, $ns, $uses);
                        if ($r !== null) {
                            $hard[$r] = true;
                        }
                    } elseif ($tj !== ',') {
                        break;
                    }
                }
                $i = $j - 1;
                break;
            case T_USE:
                $top = $stack ? $stack[count($stack) - 1] : null;
                if ($top !== 'class' && ($classDepth > 0 || $otherDepth > 0)) {
                    break; // closure use (...)
                }
                if ($top === 'class') {
                    // trait use
                    for ($j = $i + 1; $j < $n; $j++) {
                        $tj = $ty[$j];
                        if (isset(NAME_TOKENS[$tj])) {
                            if (!$declOnly) {
                                $r = resolve_name($tx[$j], $tj, $ns, $uses);
                                if ($r !== null) {
                                    $hard[$r] = true;
                                }
                            }
                        } elseif ($tj !== ',') {
                            break;
                        }
                    }
                    $i = $j - 1;
                    break;
                }
                $i = parse_import($ty, $tx, $i + 1, $n, $uses);
                break;
            default:
                if ($declOnly) {
                    break;
                }
                if ($t === T_NEW || $t === T_INSTANCEOF) {
                    if (isset(NAME_TOKENS[$ty[$i + 1] ?? 0])) {
                        $addSoft($i + 1);
                    }
                } elseif ($t === T_DOUBLE_COLON) {
                    if ($i > 0 && isset(NAME_TOKENS[$ty[$i - 1]])) {
                        $addSoft($i - 1);
                    }
                } elseif ($t === T_CATCH) {
                    for ($j = $i + 2; $j < $n && $ty[$j] !== ')' && $ty[$j] !== T_VARIABLE; $j++) {
                        if (isset(NAME_TOKENS[$ty[$j]])) {
                            $addSoft($j);
                        }
                    }
                } elseif ($t === T_FUNCTION || $t === T_FN) {
                    scan_signature($ty, $tx, $i + 1, $n, $addSoft);
                } elseif (isset(MODIFIER_TOKENS[$t]) && $stack && $stack[count($stack) - 1] === 'class') {
                    // typed property: modifiers, type, $var
                    $names = [];
                    for ($j = $i + 1; $j < $n; $j++) {
                        $tj = $ty[$j];
                        if (isset(NAME_TOKENS[$tj])) {
                            $names[] = $j;
                        } elseif ($tj === T_VARIABLE) {
                            foreach ($names as $k) {
                                $addSoft($k);
                            }
                            break;
                        } elseif (!(isset(MODIFIER_TOKENS[$tj]) || $tj === '?' || $tj === '|' || $tj === '&'
                            || $tj === '(' || $tj === ')' || $tj === T_ARRAY || $tj === T_CALLABLE
                            || $tj === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
                            || $tj === T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG)) {
                            break;
                        }
                    }
                    $i = $j - 1;
                }
                break;
        }
    }
    return [$decl, $hard, $soft];
}

/**
 * Parse a top-level `use` import starting at $i (token after T_USE).
 * Returns the index of the terminating ';' (or the last token examined).
 *
 * @param list<int|string> $ty
 * @param list<string> $tx
 * @param array<string,string> $uses
 */
function parse_import(array $ty, array $tx, int $i, int $n, array &$uses): int
{
    if (($ty[$i] ?? null) === T_FUNCTION || ($ty[$i] ?? null) === T_CONST) {
        while ($i < $n && $ty[$i] !== ';') {
            $i++;
        }
        return $i;
    }
    $prefix = '';
    $inGroup = false;
    while ($i < $n) {
        $t = $ty[$i];
        if ($t === ';') {
            return $i;
        }
        if ($t === ',' || $t === '}') {
            $i++;
            continue;
        }
        if ($inGroup && ($t === T_FUNCTION || $t === T_CONST)) {
            // skip "function x [as y]" inside a mixed group
            $i++;
            while ($i < $n && $ty[$i] !== ',' && $ty[$i] !== '}' && $ty[$i] !== ';') {
                $i++;
            }
            continue;
        }
        if (isset(NAME_TOKENS[$t])) {
            $name = ltrim($tx[$i], '\\');
            if (!$inGroup && ($ty[$i + 1] ?? null) === T_NS_SEPARATOR && ($ty[$i + 2] ?? null) === '{') {
                $prefix = $name . '\\';
                $inGroup = true;
                $i += 3;
                continue;
            }
            $full = $prefix . $name;
            $alias = ($p = strrpos($full, '\\')) !== false ? substr($full, $p + 1) : $full;
            $i++;
            if (($ty[$i] ?? null) === T_AS && isset($tx[$i + 1])) {
                $alias = $tx[$i + 1];
                $i += 2;
            }
            $uses[strtolower($alias)] = $full;
            continue;
        }
        if ($t === '{' && !$inGroup) {
            $inGroup = true; // "use \{...}" (unusual)
        } elseif ($t === '{' || $t === '(' || $t === T_VARIABLE) {
            return $i - 1; // not an import after all
        }
        $i++;
    }
    return $i;
}

/**
 * Collect parameter and return types of a function/method/closure/arrow fn signature.
 *
 * @param list<int|string> $ty
 * @param list<string> $tx
 */
function scan_signature(array $ty, array $tx, int $i, int $n, Closure $addSoft): void
{
    // Skip an optional '&' and the name (which may be a reserved word: function list()).
    $limit = $i + 2;
    while ($i < $n && $ty[$i] !== '(') {
        $t = $ty[$i];
        if ($i > $limit || $t === T_VARIABLE || $t === '{' || $t === ';' || $t === '=') {
            return;
        }
        $i++;
    }
    $depth = 0;
    $afterEq = false;
    for (; $i < $n; $i++) {
        $t = $ty[$i];
        if ($t === '(' || $t === '[' || $t === '{' || $t === T_ATTRIBUTE) {
            $depth++;
        } elseif ($t === ')' || $t === ']' || $t === '}') {
            $depth--;
            if ($depth === 0) {
                break;
            }
        } elseif ($depth === 1 && $t === ',') {
            $afterEq = false;
        } elseif ($depth === 1 && $t === '=') {
            $afterEq = true;
        } elseif ($depth === 1 && !$afterEq && isset(NAME_TOKENS[$t])) {
            $next = $ty[$i + 1] ?? null;
            if ($next !== '(' && $next !== T_DOUBLE_COLON) {
                $addSoft($i);
            }
        }
    }
    $i++;
    if (($ty[$i] ?? null) === T_USE) {
        $depth = 0;
        for ($i++; $i < $n; $i++) {
            if ($ty[$i] === '(') {
                $depth++;
            } elseif ($ty[$i] === ')' && --$depth === 0) {
                break;
            }
        }
        $i++;
    }
    if (($ty[$i] ?? null) !== ':') {
        return;
    }
    for ($i++; $i < $n; $i++) {
        $t = $ty[$i];
        if ($t === '{' || $t === ';' || $t === T_DOUBLE_ARROW) {
            return;
        }
        if (isset(NAME_TOKENS[$t])) {
            $addSoft($i);
        }
    }
}

function main(array $argv): int
{
    ini_set('memory_limit', '-1');
    $root = null;
    $universe = null;
    $fixed = [];
    $rate = null;
    $salt = null;
    $closure = 'hard+soft1';
    $manifest = null;
    $analyzeList = null;
    $args = array_slice($argv, 1);
    for ($i = 0; $i < count($args); $i++) {
        $a = $args[$i];
        $v = null;
        if (preg_match('/^(--[a-z-]+)=(.*)$/s', $a, $m)) {
            [$a, $v] = [$m[1], $m[2]];
        }
        $val = static function () use (&$i, $args, $v, $a): string {
            if ($v !== null) {
                return $v;
            }
            if (!isset($args[$i + 1])) {
                fwrite(STDERR, "make_subset: missing value for $a\n");
                usage(2);
            }
            return $args[++$i];
        };
        switch ($a) {
            case '--root': $root = $val(); break;
            case '--universe': $universe = $val(); break;
            case '--fixed-prefix': $fixed[] = $val(); break;
            case '--rate': $rate = (float)$val(); break;
            case '--salt': $salt = $val(); break;
            case '--closure': $closure = $val(); break;
            case '--manifest': $manifest = $val(); break;
            case '--analyze-list': $analyzeList = $val(); break;
            case '-h':
            case '--help': usage(0);
            default:
                fwrite(STDERR, "make_subset: unknown argument $a\n");
                usage(2);
        }
    }
    if ($root === null || $universe === null || $rate === null || $salt === null) {
        usage(2);
    }
    if (!in_array($closure, ['none', 'hard', 'hard+soft1'], true)) {
        fwrite(STDERR, "make_subset: --closure must be none, hard or hard+soft1\n");
        return 2;
    }
    if ($rate < 0 || $rate > 1) {
        fwrite(STDERR, "make_subset: --rate must be in [0, 1]\n");
        return 2;
    }
    $rootReal = realpath($root);
    if ($rootReal === false || !is_dir($rootReal)) {
        fwrite(STDERR, "make_subset: --root $root is not a directory\n");
        return 2;
    }
    $universeText = @file_get_contents($universe);
    if ($universeText === false) {
        fwrite(STDERR, "make_subset: cannot read $universe\n");
        return 2;
    }
    $start = microtime(true);

    // Normalize and dedupe the universe.
    $paths = [];
    $seen = [];
    foreach (preg_split('/\R/', $universeText) ?: [] as $line) {
        $p = trim($line);
        if ($p === '' || $p[0] === '#') {
            continue;
        }
        if (str_starts_with($p, $rootReal . '/')) {
            $p = substr($p, strlen($rootReal) + 1);
        } elseif (str_starts_with($p, rtrim($root, '/') . '/')) {
            $p = substr($p, strlen(rtrim($root, '/')) + 1);
        }
        while (str_starts_with($p, './')) {
            $p = substr($p, 2);
        }
        if (!isset($seen[$p])) {
            $seen[$p] = true;
            $paths[] = $p;
        }
    }
    unset($seen, $universeText);

    $isFixed = static function (string $p) use ($fixed): bool {
        foreach ($fixed as $prefix) {
            if (str_starts_with($p, $prefix)) {
                return true;
            }
        }
        return false;
    };

    $s = new Subset();
    $fileFixed = [];
    $exists = [];
    $tree = hash_init('sha1');
    $missing = 0;
    $tokenErrors = 0;
    $total = count($paths);
    $threshold = (int)round($rate * 1000000);
    $seeds = [];
    foreach ($paths as $fid => $p) {
        $fx = $isFixed($p);
        $fileFixed[$fid] = $fx;
        $abs = $p[0] === '/' ? $p : "$rootReal/$p";
        $code = is_file($abs) ? @file_get_contents($abs) : false;
        if ($code === false) {
            $missing++;
            $exists[$fid] = false;
            continue;
        }
        $exists[$fid] = true;
        hash_update($tree, $p . "\0" . sha1($code) . "\n");
        if (!$fx && (int)hexdec(substr(sha1($salt . $p), 0, 8)) % 1000000 < $threshold) {
            $seeds[] = $fid;
        }
        if ($fx && !preg_match('/\b(?:class|interface|trait|enum)\b/i', $code)) {
            continue;
        }
        try {
            [$decl, $hard, $soft] = scan_file($code, $fx);
        } catch (Throwable $e) {
            $tokenErrors++;
            continue;
        }
        foreach ($decl as $fq) {
            $s->declFiles[$s->nameId($fq)][] = $fid;
            $s->declCount++;
        }
        if (!$fx) {
            $h = [];
            foreach ($hard as $fq => $_) {
                $h[] = $s->nameId($fq);
            }
            $so = [];
            foreach ($soft as $fq => $_) {
                if (!isset($hard[$fq])) {
                    $so[] = $s->nameId($fq);
                }
            }
            $s->hard[$fid] = $h;
            $s->soft[$fid] = $so;
            $s->refCount += count($h) + count($so);
        }
        if (($fid + 1) % 5000 === 0) {
            fprintf(STDERR, "make_subset: indexed %d/%d files (%.1f s)\n", $fid + 1, $total, microtime(true) - $start);
        }
    }

    // name id => providing first-party file id, or -1 when a fixed file declares it.
    $provider = [];
    $duplicates = 0;
    foreach ($s->declFiles as $nid => $fids) {
        if (count($fids) > 1) {
            $duplicates++;
        }
        $best = null;
        foreach ($fids as $fid) {
            if ($fileFixed[$fid]) {
                $best = -1;
                break;
            }
            if ($best === null || strcmp($paths[$fid], $paths[$best]) < 0) {
                $best = $fid;
            }
        }
        $provider[$nid] = $best;
    }

    $selected = [];
    foreach ($seeds as $fid) {
        $selected[$fid] = true;
    }
    $unresolved = 0;
    $hardClose = static function (array $queue) use (&$selected, $s, $provider, &$unresolved): int {
        $added = 0;
        while ($queue) {
            $fid = array_pop($queue);
            foreach ($s->hard[$fid] ?? [] as $nid) {
                $p = $provider[$nid] ?? null;
                if ($p === null) {
                    $unresolved++;
                    continue;
                }
                if ($p >= 0 && !isset($selected[$p])) {
                    $selected[$p] = true;
                    $queue[] = $p;
                    $added++;
                }
            }
        }
        return $added;
    };
    $addedHard = 0;
    $addedSoft = 0;
    $addedSoftHard = 0;
    if ($closure !== 'none') {
        $addedHard = $hardClose($seeds);
    }
    $analyzeSelected = $selected; // seeds + hard closure
    if ($closure === 'hard+soft1') {
        $softAdded = [];
        foreach (array_keys($selected) as $fid) {
            foreach ($s->soft[$fid] ?? [] as $nid) {
                $p = $provider[$nid] ?? null;
                if ($p === null) {
                    $unresolved++;
                    continue;
                }
                if ($p >= 0 && !isset($selected[$p])) {
                    $selected[$p] = true;
                    $softAdded[] = $p;
                }
            }
        }
        $addedSoft = count($softAdded);
        $addedSoftHard = $hardClose($softAdded);
    }

    $toList = static function (array $set) use ($paths, $exists, $fileFixed, $rootReal): array {
        $list = [];
        foreach (array_keys($set) as $fid) {
            $p = $paths[$fid];
            if ($exists[$fid] && !$fileFixed[$fid] && is_file($p[0] === '/' ? $p : "$rootReal/$p")) {
                $list[] = $p;
            }
        }
        sort($list, SORT_STRING);
        return $list;
    };
    $out = $toList($selected);
    $outText = $out ? implode("\n", $out) . "\n" : '';
    fwrite(STDOUT, $outText);
    $analyze = $toList($analyzeSelected);
    $analyzeText = $analyze ? implode("\n", $analyze) . "\n" : '';
    if ($analyzeList !== null && file_put_contents($analyzeList, $analyzeText) === false) {
        fwrite(STDERR, "make_subset: cannot write $analyzeList\n");
        return 1;
    }

    $firstParty = count(array_filter($fileFixed, static fn(bool $f): bool => !$f));
    $info = [
        'schema' => 'phan-perf-subset/1',
        'inputs' => [
            'root' => $rootReal,
            'universe' => realpath($universe) ?: $universe,
            'universe_sha1' => sha1_file($universe),
            'tree_sha1' => hash_final($tree),
        ],
        'params' => [
            'rate' => $rate,
            'salt' => $salt,
            'closure' => $closure,
            'fixed_prefix' => $fixed,
        ],
        'counts' => [
            'universe' => $total,
            'missing' => $missing,
            'fixed' => $total - $firstParty,
            'first_party' => $firstParty,
            'seeds' => count($seeds),
            'closure_added' => $addedHard + $addedSoft + $addedSoftHard,
            'closure_added_hard' => $addedHard,
            'closure_added_soft' => $addedSoft,
            'closure_added_hard_of_soft' => $addedSoftHard,
            'output' => count($out),
            'analyze_list' => count($analyze),
            'declared_classes' => count($s->declFiles),
            'declarations' => $s->declCount,
            'duplicate_declarations' => $duplicates,
            'references' => $s->refCount,
            'unresolved_reference_visits' => $unresolved,
            'token_errors' => $tokenErrors,
        ],
        'output_sha1' => sha1($outText),
        'analyze_list_sha1' => sha1($analyzeText),
        'analyze_list_path' => $analyzeList,
        'elapsed_s' => round(microtime(true) - $start, 2),
    ];
    if ($manifest !== null) {
        file_put_contents($manifest, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
    fprintf(
        STDERR,
        "make_subset: universe=%d first_party=%d seeds=%d closure_added=%d (hard %d, soft %d, hard-of-soft %d) output=%d sha1=%s analyze_list=%d sha1=%s (%.1f s)\n",
        $total,
        $firstParty,
        count($seeds),
        $info['counts']['closure_added'],
        $addedHard,
        $addedSoft,
        $addedSoftHard,
        count($out),
        $info['output_sha1'],
        count($analyze),
        $info['analyze_list_sha1'],
        $info['elapsed_s']
    );
    return 0;
}

// Allow include() from tests without running main().
if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit(main($argv));
}
