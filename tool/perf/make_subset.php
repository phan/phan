#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Pick a deterministic, self-consistent subset of a project's first-party files
 * for benchmarking Phan.
 *
 * Usage:
 *   make_subset.php --root DIR --universe FILE [--fixed-prefix P]... --rate 0.12
 *                   --salt S [--closure none|hard|hard+soft1|hard+soft1+sig1]
 *                   [--convention SPEC]... [--manifest out.json]
 *                   [--analyze-list subset.analyze.files] > subset.files
 *
 * --universe   list of files Phan parses (e.g. from --dump-parsed-file-list), one per
 *              line, relative to --root (absolute paths under --root are accepted)
 * --fixed-prefix  files with this prefix are always parsed anyway (directory_list,
 *              e.g. vendor/); they are indexed for name resolution but never seeded
 *              and never written to either output list
 * --rate       fraction of first-party files used as seeds:
 *              hexdec(substr(sha1(salt . path), 0, 8)) % 1000000 < rate * 1000000
 * --closure    none:            seeds only
 *              hard:            + files declaring what the selection extends, implements,
 *                               uses as a trait or names first in an extends, implements,
 *                               mixin or use PHPDoc tag, transitively
 *              hard+soft1:      hard, then one level of soft references from the
 *                               hard-closed set, then the hard closure of what that added
 *              hard+soft1+sig1: hard+soft1, then one more level following only the
 *                               declaration-level references (signature types, PHPDoc
 *                               and attributes outside function bodies) of the files the
 *                               soft level added, then their hard closure
 * --convention fallback for referenced class names that no universe file declares:
 *              underscore:DIR        A_B_C       -> DIR/A/B/C.php
 *              psr4:NS\=DIR/         NS\X\Y      -> DIR/X/Y.php
 *              psr4:NS\=DIR/{1}/src/ NS\M\X\Y    -> DIR/M/src/X/Y.php
 *              (repeatable, tried in order; path matched case-insensitively against
 *              the universe)
 * --analyze-list  also write seeds + hard closure (the files worth analyzing) to this
 *              path; bench.sh turns it into include_analysis_file_list. stdout keeps the
 *              full parse selection.
 * --unresolved-out  write referenced names that resolve to nothing (TSV: name, files
 *              referencing it, kinds), most referenced first, for diagnosing the closure.
 *
 * Reference kinds (all soft unless noted):
 *   extends, implements, trait_use, doc_hard (hard); new, static_access (X::m, X::C),
 *   class_name (X::class), instanceof, catch, type (parameter, return and property
 *   types), doc (PHPDoc type expressions), attribute, function_call (calls to functions
 *   declared outside classes, resolved through `use function`, the namespace and the
 *   global fallback).
 *
 * Names are resolved best-effort (namespace, use imports, leading \). References that
 * resolve to nothing in the universe are ignored. When a name is declared both in a
 * fixed-prefix file and a first-party file, nothing is added; among several first-party
 * declarations the lexicographically smallest path wins.
 *
 * The output (stdout) is the sorted parse selection, one path per line.
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
const KINDS = [
    'extends', 'implements', 'trait_use', 'doc_hard', 'new', 'static_access', 'class_name',
    'instanceof', 'catch', 'type', 'doc', 'attribute', 'function_call',
];
const CATEGORIES = ['first_party', 'fixed', 'convention', 'internal', 'unresolved'];
// PHPDoc tags whose type expressions are followed. Prefixed variants (phan-, psalm-,
// phpstan-) of every tag in DOC_TAGS are accepted; unprefixed only those in DOC_UNPREFIXED.
const DOC_TAGS = [
    'param' => true, 'return' => true, 'var' => true, 'property' => true, 'property-read' => true,
    'property-write' => true, 'method' => true, 'throws' => true, 'mixin' => true, 'extends' => true,
    'implements' => true, 'use' => true, 'template' => true, 'template-covariant' => true,
    'template-contravariant' => true, 'template-extends' => true, 'template-implements' => true,
    'template-use' => true, 'type' => true, 'import-type' => true, 'assert' => true,
    'assert-if-true' => true, 'assert-if-false' => true, 'self-out' => true, 'this-out' => true,
    'param-out' => true, 'closure-scope' => true, 'real-return' => true,
    'assert-true-condition' => true, 'assert-false-condition' => true,
];
const DOC_UNPREFIXED = [
    'param' => true, 'return' => true, 'var' => true, 'property' => true, 'property-read' => true,
    'property-write' => true, 'method' => true, 'throws' => true, 'mixin' => true, 'extends' => true,
    'implements' => true, 'use' => true, 'template' => true, 'template-covariant' => true,
    'template-contravariant' => true, 'template-extends' => true, 'template-implements' => true,
    'template-use' => true,
];
// The first class named by these tags is a hard reference (the rest of the tag is soft).
const DOC_HARD_TAGS = [
    'extends' => true, 'implements' => true, 'mixin' => true, 'use' => true,
    'template-extends' => true, 'template-implements' => true, 'template-use' => true,
];
const DOC_STOP_WORDS = [
    'int' => true, 'integer' => true, 'float' => true, 'double' => true, 'string' => true,
    'bool' => true, 'boolean' => true, 'array' => true, 'list' => true, 'callable' => true,
    'iterable' => true, 'object' => true, 'mixed' => true, 'void' => true, 'never' => true,
    'null' => true, 'false' => true, 'true' => true, 'resource' => true, 'scalar' => true,
    'numeric' => true, 'self' => true, 'static' => true, 'parent' => true, 'this' => true,
    'max' => true, 'min' => true, 'is' => true, 'not' => true, 'of' => true, 'as' => true,
    'noreturn' => true, 'empty' => true,
];

function usage(int $code): never
{
    fwrite($code ? STDERR : STDOUT, <<<'EOT'
Usage: make_subset.php --root DIR --universe FILE [--fixed-prefix P]... --rate R --salt S
                       [--closure none|hard|hard+soft1|hard+soft1+sig1]
                       [--convention underscore:DIR | psr4:NS\=DIR[/{1}/...]]...
                       [--manifest out.json] [--analyze-list subset.analyze.files]
                       [--unresolved-out unresolved.tsv] > subset.files

EOT);
    exit($code);
}

final class Subset
{
    /** @var array<string,int> lowercase key => id (classes: FQCN, functions: "fn:" FQN) */
    public array $nameIds = [];
    /** @var list<string> id => key as first seen */
    public array $names = [];
    /** @var array<int,list<int>> name id => declaring file ids */
    public array $declFiles = [];
    /** @var array<int,int> "fn:Ns\f" id => "fn:f" id (unqualified call, global fallback) */
    public array $fallback = [];
    /** @var array<int,true> name ids referenced by some first-party file */
    public array $referenced = [];
    /** @var array<int,list<int>> file id => hard ref name ids */
    public array $hard = [];
    /** @var array<int,list<int>> file id => declaration-level soft ref name ids */
    public array $sig = [];
    /** @var array<int,list<int>> file id => other soft ref name ids */
    public array $body = [];
    /** @var array<string,array<int,int>> kind => name id => number of files */
    public array $kindRefs = [];
    public int $declCount = 0;
    public int $fdeclCount = 0;

    public function nameId(string $key): int
    {
        $lower = strtolower($key);
        $id = $this->nameIds[$lower] ?? null;
        if ($id === null) {
            $id = count($this->names);
            $this->nameIds[$lower] = $id;
            $this->names[] = $key;
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
            $pos = (int)strpos($name, '\\');
            $first = strtolower(substr($name, 0, $pos));
            if (isset($uses[$first])) {
                return $uses[$first] . substr($name, $pos);
            }
            return $ns !== '' ? "$ns\\$name" : $name;
    }
    return null;
}

/**
 * Scan one file.
 *
 * @return array{decl:list<string>,fdecl:list<string>,refs:array<string,array<string,string>>,fallback:array<string,string>}
 *   refs: key (FQCN, or "fn:" + function FQN) => kind => context ('hard', 'sig' or 'body')
 */
function scan_file(string $code, bool $declOnly): array
{
    // Significant tokens only; doc comments are kept aside with the index of the next token.
    $ty = [];
    $tx = [];
    $docs = [];
    foreach (token_get_all($code) as $t) {
        if (is_array($t)) {
            $id = $t[0];
            if ($id === T_DOC_COMMENT) {
                if (!$declOnly) {
                    $docs[] = [count($ty), $t[1]];
                }
                continue;
            }
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_INLINE_HTML
                || $id === T_OPEN_TAG || $id === T_CLOSE_TAG) {
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
    $nd = count($docs);
    $di = 0;

    $decl = [];
    $fdecl = [];
    $refs = [];
    $fallback = [];
    $ns = '';
    $uses = [];
    $fnUses = [];
    $templates = [];
    $stack = [];          // 'ns' | 'class' | 'fn' | 'other'
    $classDepth = 0;
    $otherDepth = 0;      // 'other' and 'fn' entries
    $fnDepth = 0;         // function bodies
    $pending = null;      // what the next '{' opens: 'ns' | 'class'
    $pendingFn = false;   // a function signature was seen; its body '{' is next
    $attrName = [];       // token indexes of attribute class names

    $add = static function (?string $key, string $kind, string $ctx) use (&$refs): void {
        if ($key === null || $key === '') {
            return;
        }
        $cur = $refs[$key][$kind] ?? null;
        if ($cur === null || ($cur === 'body' && $ctx === 'sig')) {
            $refs[$key][$kind] = $ctx;
        }
    };
    $ctx = static function () use (&$fnDepth): string {
        return $fnDepth > 0 ? 'body' : 'sig';
    };
    $addName = static function (int $i, string $kind) use ($ty, $tx, &$ns, &$uses, $add, $ctx): void {
        $add(resolve_name($tx[$i], $ty[$i], $ns, $uses), $kind, $ctx());
    };

    for ($i = 0; $i < $n; $i++) {
        while ($di < $nd && $docs[$di][0] <= $i) {
            scan_doc($docs[$di][1], $ns, $uses, $templates, $add, $ctx());
            $di++;
        }
        $t = $ty[$i];
        switch ($t) {
            case '{':
            case T_CURLY_OPEN:
            case T_DOLLAR_OPEN_CURLY_BRACES:
                if ($t === '{' && $pending !== null) {
                    $kind = $pending;
                } elseif ($t === '{' && $pendingFn) {
                    $kind = 'fn';
                } else {
                    $kind = 'other';
                }
                $pending = null;
                $pendingFn = false;
                $stack[] = $kind;
                if ($kind === 'class') {
                    $classDepth++;
                } elseif ($kind === 'fn') {
                    $fnDepth++;
                    $otherDepth++;
                } elseif ($kind === 'other') {
                    $otherDepth++;
                }
                break;
            case '}':
                $kind = array_pop($stack);
                if ($kind === 'class') {
                    $classDepth--;
                } elseif ($kind === 'fn') {
                    $fnDepth--;
                    $otherDepth--;
                } elseif ($kind === 'other') {
                    $otherDepth--;
                }
                break;
            case ';':
                $pendingFn = false; // abstract / interface method
                break;
            case T_NAMESPACE:
                $next = $ty[$i + 1] ?? null;
                if ($next === T_STRING || $next === T_NAME_QUALIFIED) {
                    $ns = $tx[$i + 1];
                    $uses = [];
                    $fnUses = [];
                    $i++;
                    if (($ty[$i + 1] ?? null) === '{') {
                        $pending = 'ns';
                    }
                } elseif ($next === '{') {
                    $ns = '';
                    $uses = [];
                    $fnUses = [];
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
            case T_FUNCTION:
                $j = $i + 1;
                $tj = $ty[$j] ?? null;
                if ($tj === '&' || $tj === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
                    || $tj === T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG) {
                    $j++;
                }
                $top = $stack ? $stack[count($stack) - 1] : null;
                if (($ty[$j] ?? null) === T_STRING && ($ty[$j + 1] ?? null) === '(' && $top !== 'class') {
                    $fdecl[] = $ns !== '' ? $ns . '\\' . $tx[$j] : $tx[$j];
                }
                if (!$declOnly) {
                    scan_signature($ty, $tx, $i + 1, $n, static function (int $k) use ($addName): void {
                        $addName($k, 'type');
                    });
                }
                $pendingFn = true;
                break;
            case T_FN:
                if (!$declOnly) {
                    scan_signature($ty, $tx, $i + 1, $n, static function (int $k) use ($addName): void {
                        $addName($k, 'type');
                    });
                }
                break;
            case T_EXTENDS:
            case T_IMPLEMENTS:
                $kind = $t === T_EXTENDS ? 'extends' : 'implements';
                for ($j = $i + 1; $j < $n; $j++) {
                    $tj = $ty[$j];
                    if (isset(NAME_TOKENS[$tj])) {
                        if (!$declOnly) {
                            $add(resolve_name($tx[$j], $tj, $ns, $uses), $kind, 'hard');
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
                    for ($j = $i + 1; $j < $n; $j++) {
                        $tj = $ty[$j];
                        if (isset(NAME_TOKENS[$tj])) {
                            if (!$declOnly) {
                                $add(resolve_name($tx[$j], $tj, $ns, $uses), 'trait_use', 'hard');
                            }
                        } elseif ($tj !== ',') {
                            break;
                        }
                    }
                    $i = $j - 1;
                    break;
                }
                $i = parse_import($ty, $tx, $i + 1, $n, $uses, $fnUses);
                break;
            case T_ATTRIBUTE:
                if ($declOnly) {
                    break;
                }
                // #[A, B(args)]: names at depth 0 that start an attribute are classes.
                $depth = 0;
                $expect = true;
                for ($j = $i + 1; $j < $n; $j++) {
                    $tj = $ty[$j];
                    if ($depth === 0 && $expect && isset(NAME_TOKENS[$tj])) {
                        $addName($j, 'attribute');
                        $attrName[$j] = true;
                        $expect = false;
                    } elseif ($tj === '(' || $tj === '[' || $tj === T_ATTRIBUTE) {
                        $depth++;
                    } elseif ($tj === ')' || $tj === ']') {
                        if ($depth === 0) {
                            break;
                        }
                        $depth--;
                    } elseif ($tj === ',' && $depth === 0) {
                        $expect = true;
                    }
                }
                break; // the arguments are scanned by the main loop
            default:
                if ($declOnly) {
                    break;
                }
                if ($t === T_NEW || $t === T_INSTANCEOF) {
                    if (isset(NAME_TOKENS[$ty[$i + 1] ?? 0])) {
                        $addName($i + 1, $t === T_NEW ? 'new' : 'instanceof');
                    }
                } elseif ($t === T_DOUBLE_COLON) {
                    if ($i > 0 && isset(NAME_TOKENS[$ty[$i - 1]])) {
                        $addName($i - 1, ($ty[$i + 1] ?? null) === T_CLASS ? 'class_name' : 'static_access');
                    }
                } elseif ($t === T_CATCH) {
                    for ($j = $i + 2; $j < $n && $ty[$j] !== ')' && $ty[$j] !== T_VARIABLE; $j++) {
                        if (isset(NAME_TOKENS[$ty[$j]])) {
                            $addName($j, 'catch');
                        }
                    }
                } elseif (isset(MODIFIER_TOKENS[$t]) && $stack && $stack[count($stack) - 1] === 'class') {
                    // typed property: modifiers, type, $var
                    $names = [];
                    for ($j = $i + 1; $j < $n; $j++) {
                        $tj = $ty[$j];
                        if (isset(NAME_TOKENS[$tj])) {
                            $names[] = $j;
                        } elseif ($tj === T_VARIABLE) {
                            foreach ($names as $k) {
                                $addName($k, 'type');
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
                } elseif (isset(NAME_TOKENS[$t]) && ($ty[$i + 1] ?? null) === '(' && !isset($attrName[$i])) {
                    // function call
                    $prev = $ty[$i - 1] ?? null;
                    if ($prev === T_OBJECT_OPERATOR || $prev === T_NULLSAFE_OBJECT_OPERATOR
                        || $prev === T_DOUBLE_COLON || $prev === T_NEW || $prev === T_FUNCTION
                        || $prev === T_FN || $prev === T_CONST
                        || (($prev === '&' || $prev === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)
                            && ($ty[$i - 2] ?? null) === T_FUNCTION)) {
                        break;
                    }
                    $name = $tx[$i];
                    switch ($t) {
                        case T_NAME_FULLY_QUALIFIED:
                            $key = 'fn:' . substr($name, 1);
                            break;
                        case T_NAME_RELATIVE:
                            $key = 'fn:' . ($ns !== '' ? $ns . '\\' : '') . substr($name, 10);
                            break;
                        case T_NAME_QUALIFIED:
                            $r = resolve_name($name, $t, $ns, $uses);
                            $key = 'fn:' . $r;
                            break;
                        default:
                            $lower = strtolower($name);
                            if (isset($fnUses[$lower])) {
                                $key = 'fn:' . $fnUses[$lower];
                            } elseif ($ns === '') {
                                $key = 'fn:' . $name;
                            } else {
                                $key = 'fn:' . $ns . '\\' . $name;
                                $fallback[$key] = 'fn:' . $name;
                            }
                    }
                    $add($key, 'function_call', $ctx());
                }
                break;
        }
    }
    while ($di < $nd) {
        scan_doc($docs[$di][1], $ns, $uses, $templates, $add, $ctx());
        $di++;
    }
    return ['decl' => $decl, 'fdecl' => $fdecl, 'refs' => $refs, 'fallback' => $fallback];
}

/**
 * Parse a top-level `use` import starting at $i (token after T_USE).
 * Returns the index of the terminating ';' (or the last token examined).
 *
 * @param list<int|string> $ty
 * @param list<string> $tx
 * @param array<string,string> $uses class/namespace imports (lowercase alias => name)
 * @param array<string,string> $fnUses function imports (lowercase alias => FQN)
 */
function parse_import(array $ty, array $tx, int $i, int $n, array &$uses, array &$fnUses): int
{
    $mode = 'class';
    if (($ty[$i] ?? null) === T_FUNCTION) {
        $mode = 'function';
        $i++;
    } elseif (($ty[$i] ?? null) === T_CONST) {
        $mode = 'const';
        $i++;
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
        $itemMode = $mode;
        if ($inGroup && ($t === T_FUNCTION || $t === T_CONST)) {
            // mixed group: use A\{B, function c, const D}
            $itemMode = $t === T_FUNCTION ? 'function' : 'const';
            $i++;
            if ($i >= $n) {
                break;
            }
            $t = $ty[$i];
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
            if ($itemMode === 'class') {
                $uses[strtolower($alias)] = $full;
            } elseif ($itemMode === 'function') {
                $fnUses[strtolower($alias)] = $full;
            }
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
function scan_signature(array $ty, array $tx, int $i, int $n, Closure $addType): void
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
                $addType($i);
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
            $addType($i);
        }
    }
}

/**
 * Collect class names from the type expressions of a PHPDoc comment.
 *
 * @param array<string,string> $uses
 * @param array<string,true> $templates template names seen so far in the file (lowercase)
 */
function scan_doc(string $doc, string $ns, array $uses, array &$templates, Closure $add, string $ctx): void
{
    if (!str_contains($doc, '@')
        || !preg_match_all('/^[ \t]*(?:\/\*\*|\*)?[ \t]*@((?:phan-|psalm-|phpstan-)?[a-z][a-z-]*)[ \t]+([^\r\n]*)/im', $doc, $mm, PREG_SET_ORDER)) {
        return;
    }
    foreach ($mm as $m) {
        $tag = strtolower($m[1]);
        $base = (string)preg_replace('/^(?:phan|psalm|phpstan)-/', '', $tag);
        $prefixed = $base !== $tag;
        if (!isset(DOC_TAGS[$base]) || (!$prefixed && !isset(DOC_UNPREFIXED[$base]))) {
            continue;
        }
        $rest = (string)preg_replace('/\s*\*+\/\s*$/', '', $m[2]);
        $isMethod = false;
        $expr = '';
        switch ($base) {
            case 'template':
            case 'template-covariant':
            case 'template-contravariant':
                if (!preg_match('/^(\w+)(?:\s+(?:of|as)\s+(.*))?/', ltrim($rest), $t)) {
                    continue 2;
                }
                $templates[strtolower($t[1])] = true;
                $expr = isset($t[2]) ? doc_type_expr($t[2]) : '';
                break;
            case 'type':
                $p = strpos($rest, '=');
                if ($p === false) {
                    continue 2;
                }
                $expr = substr($rest, $p + 1);
                break;
            case 'import-type':
                if (!preg_match('/\bfrom\s+(\S+)/', $rest, $t)) {
                    continue 2;
                }
                $expr = $t[1];
                break;
            case 'method':
                $isMethod = true;
                $expr = doc_method_expr($rest);
                break;
            default:
                $expr = doc_type_expr($rest);
        }
        $hardTag = isset(DOC_HARD_TAGS[$base]);
        $first = true;
        foreach (doc_names($expr, $isMethod, $templates) as $name) {
            $type = $name[0] === '\\' ? T_NAME_FULLY_QUALIFIED : (str_contains($name, '\\') ? T_NAME_QUALIFIED : T_STRING);
            $r = resolve_name($name, $type, $ns, $uses);
            if ($r === null) {
                continue;
            }
            if ($hardTag && $first) {
                $add($r, 'doc_hard', 'hard');
            } else {
                $add($r, 'doc', $ctx);
            }
            $first = false;
        }
    }
}

/**
 * The type expression at the start of a tag's text: balanced over <>, (), {}, [] and
 * quotes, continuing across whitespace around |, & and a callable's return ':'.
 */
function doc_type_expr(string $s): string
{
    $s = ltrim($s);
    $len = strlen($s);
    $depth = 0;
    $quote = null;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            $out .= $c;
            if ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif ($c === '<' || $c === '(' || $c === '{' || $c === '[') {
            $depth++;
        } elseif ($c === '>' || $c === ')' || $c === '}' || $c === ']') {
            $depth--;
        } elseif (($c === ' ' || $c === "\t") && $depth <= 0) {
            $j = $i;
            while ($j < $len && ($s[$j] === ' ' || $s[$j] === "\t")) {
                $j++;
            }
            $prev = substr(rtrim($out), -1);
            $next = $s[$j] ?? '';
            if ($prev === '|' || $prev === '&' || $prev === ':' || $next === '|' || $next === '&'
                || ($next === ':' && ($s[$j + 1] ?? '') !== ':')) {
                $out .= ' ';
                $i = $j - 1;
                continue;
            }
            break;
        }
        $out .= $c;
    }
    return $out;
}

/**
 * "@method [static] Ret name(Type $p, ...) [: Ret] description" -> up to the parameter list.
 */
function doc_method_expr(string $s): string
{
    $p = strpos($s, '(');
    if ($p === false) {
        return $s;
    }
    $depth = 0;
    $len = strlen($s);
    for ($i = $p; $i < $len; $i++) {
        if ($s[$i] === '(') {
            $depth++;
        } elseif ($s[$i] === ')' && --$depth === 0) {
            $head = substr($s, 0, $i + 1);
            if (preg_match('/^\s*:\s*(\S+)/', substr($s, $i + 1), $m)) {
                $head .= ' ' . $m[1];
            }
            return $head;
        }
    }
    return $s;
}

/**
 * Class-like names in a PHPDoc type expression.
 *
 * @param array<string,true> $templates
 * @return list<string>
 */
function doc_names(string $expr, bool $isMethod, array $templates): array
{
    if ($expr === '') {
        return [];
    }
    $expr = (string)preg_replace('/\'[^\']*\'|"[^"]*"/', "''", $expr);
    if (!preg_match_all('/\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*/', $expr, $m, PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $out = [];
    foreach ($m[0] as [$name, $off]) {
        $end = $off + strlen($name);
        $prev = $off > 0 ? $expr[$off - 1] : '';
        $next = $expr[$end] ?? '';
        if ($prev === '$' || $prev === '-' || $next === '-'
            || ($prev === ':' && $off > 1 && $expr[$off - 2] === ':')) {
            continue; // variable, hyphenated keyword (class-string), Foo::CONST
        }
        if (($next === ':' && ($expr[$end + 1] ?? '') !== ':')
            || ($next === '?' && ($expr[$end + 1] ?? '') === ':')) {
            continue; // array shape key
        }
        if ($isMethod && $next === '(') {
            continue; // method name
        }
        $lower = strtolower(ltrim($name, '\\'));
        if (isset(DOC_STOP_WORDS[$lower]) || isset($templates[$lower])) {
            continue;
        }
        $out[] = $name;
    }
    return $out;
}

/**
 * @return array{0:string,1:string,2:string}
 */
function parse_convention(string $spec): array
{
    if (preg_match('/^underscore:(.*)$/s', $spec, $m)) {
        $dir = trim($m[1], '/');
        return ['underscore', $dir === '' || $dir === '.' ? '' : "$dir/", ''];
    }
    if (preg_match('/^psr4:(.+?)=(.*)$/s', $spec, $m)) {
        $dir = trim($m[2], '/');
        return ['psr4', strtolower(trim($m[1], '\\')) . '\\', $dir === '' || $dir === '.' ? '' : "$dir/"];
    }
    fwrite(STDERR, "make_subset: bad --convention '$spec' (expected underscore:DIR or psr4:NS\\=DIR)\n");
    exit(2);
}

/**
 * @param array{0:string,1:string,2:string} $conv
 */
function convention_path(array $conv, string $fqcn): ?string
{
    [$kind, $a, $b] = $conv;
    if ($kind === 'underscore') {
        if (str_contains($fqcn, '\\')) {
            return null;
        }
        return $a . str_replace('_', '/', $fqcn) . '.php';
    }
    if (strncasecmp($fqcn, $a, strlen($a)) !== 0) {
        return null;
    }
    $segs = explode('\\', substr($fqcn, strlen($a)));
    $tpl = $b;
    if (str_contains($tpl, '{1}')) {
        if (count($segs) < 2) {
            return null;
        }
        $tpl = str_replace('{1}', (string)array_shift($segs), $tpl);
    }
    return $tpl . implode('/', $segs) . '.php';
}

function is_internal_class(string $name): bool
{
    foreach (['class_exists', 'interface_exists', 'trait_exists', 'enum_exists'] as $f) {
        if ($f($name, false)) {
            return (new ReflectionClass($name))->isInternal();
        }
    }
    return false;
}

function is_internal_function(string $name): bool
{
    return function_exists($name) && (new ReflectionFunction($name))->isInternal();
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
    $conventionSpecs = [];
    $unresolvedOut = null;
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
            case '--convention': $conventionSpecs[] = $val(); break;
            case '--unresolved-out': $unresolvedOut = $val(); break;
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
    if (!in_array($closure, ['none', 'hard', 'hard+soft1', 'hard+soft1+sig1'], true)) {
        fwrite(STDERR, "make_subset: --closure must be none, hard, hard+soft1 or hard+soft1+sig1\n");
        return 2;
    }
    if ($rate < 0 || $rate > 1) {
        fwrite(STDERR, "make_subset: --rate must be in [0, 1]\n");
        return 2;
    }
    $conventions = array_map('parse_convention', $conventionSpecs);
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
        if ($fx && !preg_match('/\b(?:class|interface|trait|enum|function)\b/i', $code)) {
            continue;
        }
        try {
            $res = scan_file($code, $fx);
        } catch (Throwable $e) {
            $tokenErrors++;
            continue;
        }
        foreach ($res['decl'] as $fq) {
            $s->declFiles[$s->nameId($fq)][] = $fid;
            $s->declCount++;
        }
        foreach ($res['fdecl'] as $fq) {
            $s->declFiles[$s->nameId('fn:' . $fq)][] = $fid;
            $s->fdeclCount++;
        }
        if (!$fx) {
            $h = [];
            $sig = [];
            $body = [];
            foreach ($res['refs'] as $key => $kinds) {
                $nid = $s->nameId($key);
                $s->referenced[$nid] = true;
                foreach ($kinds as $kind => $c) {
                    $s->kindRefs[$kind][$nid] = ($s->kindRefs[$kind][$nid] ?? 0) + 1;
                    if ($c === 'hard') {
                        $h[$nid] = true;
                    } elseif ($c === 'sig') {
                        $sig[$nid] = true;
                    } else {
                        $body[$nid] = true;
                    }
                }
            }
            foreach ($res['fallback'] as $key => $global) {
                $s->fallback[$s->nameId($key)] = $s->nameId($global);
            }
            $sig = array_diff_key($sig, $h);
            $body = array_diff_key($body, $h, $sig);
            $s->hard[$fid] = array_keys($h);
            $s->sig[$fid] = array_keys($sig);
            $s->body[$fid] = array_keys($body);
        }
        if (($fid + 1) % 5000 === 0) {
            fprintf(STDERR, "make_subset: indexed %d/%d files (%.1f s)\n", $fid + 1, $total, microtime(true) - $start);
        }
    }

    // Case-insensitive path index for --convention (smallest path wins).
    $pathIndex = [];
    if ($conventions) {
        $order = array_keys($paths);
        usort($order, static fn(int $x, int $y): int => strcmp($paths[$x], $paths[$y]));
        foreach ($order as $fid) {
            if ($exists[$fid]) {
                $pathIndex[strtolower($paths[$fid])] ??= $fid;
            }
        }
    }
    $choose = static function (array $fids) use ($fileFixed, $paths): int {
        $best = null;
        foreach ($fids as $fid) {
            if ($fileFixed[$fid]) {
                return -1;
            }
            if ($best === null || strcmp($paths[$fid], $paths[$best]) < 0) {
                $best = $fid;
            }
        }
        return (int)$best;
    };

    // name id => provider (file id, -1 = fixed file, null = none) and category.
    $providerOf = [];
    $catOf = [];
    $duplicates = 0;
    foreach ($s->declFiles as $fids) {
        if (count($fids) > 1) {
            $duplicates++;
        }
    }
    $nameCats = array_fill_keys(CATEGORIES, 0);
    foreach (array_keys($s->referenced) as $nid) {
        $name = $s->names[$nid];
        $isFn = str_starts_with($name, 'fn:');
        $fids = $s->declFiles[$nid] ?? null;
        if ($fids === null && isset($s->fallback[$nid])) {
            $fids = $s->declFiles[$s->fallback[$nid]] ?? null;
        }
        $p = null;
        if ($fids !== null) {
            $p = $choose($fids);
            $cat = $p < 0 ? 'fixed' : 'first_party';
        } else {
            $cat = null;
            if (!$isFn) {
                foreach ($conventions as $conv) {
                    $path = convention_path($conv, $name);
                    if ($path !== null && isset($pathIndex[strtolower($path)])) {
                        $f = $pathIndex[strtolower($path)];
                        $p = $fileFixed[$f] ? -1 : $f;
                        $cat = 'convention';
                        break;
                    }
                }
            }
            if ($cat === null) {
                $plain = $isFn ? substr(isset($s->fallback[$nid]) ? $s->names[$s->fallback[$nid]] : $name, 3) : $name;
                $cat = ($isFn ? is_internal_function($plain) : is_internal_class($plain)) ? 'internal' : 'unresolved';
            }
        }
        $providerOf[$nid] = $p;
        $catOf[$nid] = $cat;
        $nameCats[$cat]++;
    }

    $selected = [];
    foreach ($seeds as $fid) {
        $selected[$fid] = true;
    }
    $viaConvention = 0;
    $take = static function (int $nid) use (&$selected, $providerOf, $catOf, &$viaConvention): ?int {
        $p = $providerOf[$nid] ?? null;
        if ($p === null || $p < 0 || isset($selected[$p])) {
            return null;
        }
        $selected[$p] = true;
        if ($catOf[$nid] === 'convention') {
            $viaConvention++;
        }
        return $p;
    };
    $hardClose = static function (array $queue) use ($s, $take): int {
        $added = 0;
        while ($queue) {
            $fid = array_pop($queue);
            foreach ($s->hard[$fid] ?? [] as $nid) {
                $p = $take($nid);
                if ($p !== null) {
                    $queue[] = $p;
                    $added++;
                }
            }
        }
        return $added;
    };
    $added = ['hard' => 0, 'soft' => 0, 'hard_of_soft' => 0, 'sig' => 0, 'hard_of_sig' => 0];
    if ($closure !== 'none') {
        $added['hard'] = $hardClose($seeds);
    }
    $analyzeSelected = $selected; // seeds + hard closure
    if ($closure === 'hard+soft1' || $closure === 'hard+soft1+sig1') {
        $softAdded = [];
        foreach (array_keys($selected) as $fid) {
            foreach ([$s->sig[$fid] ?? [], $s->body[$fid] ?? []] as $list) {
                foreach ($list as $nid) {
                    $p = $take($nid);
                    if ($p !== null) {
                        $softAdded[] = $p;
                    }
                }
            }
        }
        $added['soft'] = count($softAdded);
        $added['hard_of_soft'] = $hardClose($softAdded);
        if ($closure === 'hard+soft1+sig1') {
            $sigAdded = [];
            foreach (array_keys(array_diff_key($selected, $analyzeSelected)) as $fid) {
                foreach ($s->sig[$fid] ?? [] as $nid) {
                    $p = $take($nid);
                    if ($p !== null) {
                        $sigAdded[] = $p;
                    }
                }
            }
            $added['sig'] = count($sigAdded);
            $added['hard_of_sig'] = $hardClose($sigAdded);
        }
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

    // Per reference kind: number of (file, name) references by resolution category.
    $refKinds = [];
    foreach (KINDS as $kind) {
        $refKinds[$kind] = ['refs' => 0] + array_fill_keys(CATEGORIES, 0);
        foreach ($s->kindRefs[$kind] ?? [] as $nid => $count) {
            $refKinds[$kind]['refs'] += $count;
            $refKinds[$kind][$catOf[$nid]] += $count;
        }
    }

    if ($unresolvedOut !== null) {
        $rows = [];
        foreach ($catOf as $nid => $cat) {
            if ($cat !== 'unresolved') {
                continue;
            }
            $files = 0;
            $kinds = [];
            foreach ($s->kindRefs as $kind => $m) {
                if (isset($m[$nid])) {
                    $files = max($files, $m[$nid]);
                    $kinds[] = "$kind:" . $m[$nid];
                }
            }
            $rows[] = [$s->names[$nid], $files, implode(',', $kinds)];
        }
        usort($rows, static fn(array $x, array $y): int => [$y[1], $x[0]] <=> [$x[1], $y[0]]);
        $tsv = '';
        foreach ($rows as $row) {
            $tsv .= implode("\t", $row) . "\n";
        }
        file_put_contents($unresolvedOut, $tsv);
    }

    $firstParty = count(array_filter($fileFixed, static fn(bool $f): bool => !$f));
    $info = [
        'schema' => 'phan-perf-subset/2',
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
            'conventions' => $conventionSpecs,
        ],
        'counts' => [
            'universe' => $total,
            'missing' => $missing,
            'fixed' => $total - $firstParty,
            'first_party' => $firstParty,
            'seeds' => count($seeds),
            'closure_added' => array_sum($added),
            'closure_added_hard' => $added['hard'],
            'closure_added_soft' => $added['soft'],
            'closure_added_hard_of_soft' => $added['hard_of_soft'],
            'closure_added_sig' => $added['sig'],
            'closure_added_hard_of_sig' => $added['hard_of_sig'],
            'closure_added_via_convention' => $viaConvention,
            'output' => count($out),
            'analyze_list' => count($analyze),
            'declared_classes' => count(array_filter(array_keys($s->declFiles), static fn(int $nid): bool => !str_starts_with($s->names[$nid], 'fn:'))),
            'declared_functions' => count(array_filter(array_keys($s->declFiles), static fn(int $nid): bool => str_starts_with($s->names[$nid], 'fn:'))),
            'class_declarations' => $s->declCount,
            'function_declarations' => $s->fdeclCount,
            'duplicate_declarations' => $duplicates,
            'token_errors' => $tokenErrors,
        ],
        // Distinct referenced names by how they resolved.
        'referenced_names' => $nameCats,
        'ref_kinds' => $refKinds,
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
        "make_subset: universe=%d first_party=%d seeds=%d closure_added=%d (hard %d, soft %d, hard-of-soft %d, sig %d, hard-of-sig %d; via convention %d) output=%d sha1=%s analyze_list=%d sha1=%s (%.1f s)\n",
        $total,
        $firstParty,
        count($seeds),
        $info['counts']['closure_added'],
        $added['hard'],
        $added['soft'],
        $added['hard_of_soft'],
        $added['sig'],
        $added['hard_of_sig'],
        $viaConvention,
        count($out),
        $info['output_sha1'],
        count($analyze),
        $info['analyze_list_sha1'],
        $info['elapsed_s']
    );
    fprintf(
        STDERR,
        "make_subset: referenced names: %s\n",
        implode(' ', array_map(static fn(string $c): string => "$c=" . $nameCats[$c], CATEGORIES))
    );
    return 0;
}

// Allow include() from tests without running main().
if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit(main($argv));
}
