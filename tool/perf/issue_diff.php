#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compare two Phan text-mode issue outputs (`-m text --no-color`).
 *
 * Usage: issue_diff.php A.txt B.txt [--examples N]
 *
 * Both files are normalized (ANSI escapes stripped, rtrim, empty lines dropped,
 * duplicates kept) and sorted with strcmp.
 *
 *   IDENTICAL <n> <sha1>              exit 0
 *   only suggestion-only diffs        exit 2
 *   any real or union-order-only diff exit 1
 *   (UnionType::__toString sorts components, so an order change means printing
 *   or type construction changed; it is reported separately but gates like real)
 *   usage / IO error       exit 3
 *
 * Differences are grouped by "file:line IssueType" and each group is classified:
 *   suggestion-only   equal after stripping the trailing " (<suggestion>)" that
 *                     PlainTextPrinter appends (e.g. "(Did you mean ...)",
 *                     "(Types inferred after analysis: ...)"); a trailing
 *                     " (at column N)" is not a suggestion and is kept
 *                     (see issue_lib.php)
 *   union-order-only  equal after sorting the |-separated components of union
 *                     types (applied on top of the suggestion stripping)
 *   real              anything else (including a different number of issues)
 *
 * The sha1 is sha1(implode("\n", sorted_lines) . "\n"), the same value that
 * bench_summarize.php reports as issues_sha1.
 */

const CLASS_REAL = 'real';
const CLASS_SUGGESTION = 'suggestion-only';
const CLASS_UNION = 'union-order-only';

require_once __DIR__ . '/issue_lib.php';

/**
 * @return list<string>
 */
function load_issue_lines(string $path): array
{
    $lines = issue_load_lines($path);
    if ($lines === null) {
        fwrite(STDERR, "issue_diff: cannot open $path\n");
        exit(3);
    }
    return $lines;
}

/**
 * @return array{0:string,1:string} [group key, issue type]
 */
function group_key(string $line): array
{
    if (preg_match('/^(\S+:\d+) (\S+)/', $line, $m)) {
        return [$m[1] . ' ' . $m[2], $m[2]];
    }
    return ['(unparsed) ' . $line, '(unparsed)'];
}

/**
 * Sort union components at every bracket level of a type token.
 * Commas inside brackets separate positional elements (kept in order);
 * shape keys ("key:type" inside {}) are kept attached to their value.
 */
function normalize_type(string $t): string
{
    $parts = split_top_level($t, '|');
    foreach ($parts as $i => $part) {
        $parts[$i] = normalize_nested($part);
    }
    if (count($parts) > 1) {
        sort($parts, SORT_STRING);
    }
    return implode('|', $parts);
}

function normalize_nested(string $part): string
{
    $out = '';
    $len = strlen($part);
    $i = 0;
    while ($i < $len) {
        $c = $part[$i];
        if ($c === '<' || $c === '{' || $c === '(' || $c === '[') {
            $close = find_close($part, $i);
            if ($close === null) {
                return $part;
            }
            $inner = substr($part, $i + 1, $close - $i - 1);
            $elements = split_top_level($inner, ',');
            foreach ($elements as $k => $el) {
                if ($c === '{' && preg_match('/^([\w\'"\- ]+\??):(.*)$/s', $el, $m)) {
                    $elements[$k] = $m[1] . ':' . normalize_type($m[2]);
                } else {
                    $elements[$k] = normalize_type($el);
                }
            }
            $out .= $c . implode(',', $elements) . $part[$close];
            $i = $close + 1;
            continue;
        }
        $out .= $c;
        $i++;
    }
    return $out;
}

function find_close(string $s, int $open): ?int
{
    $depth = 0;
    $len = strlen($s);
    for ($i = $open; $i < $len; $i++) {
        $c = $s[$i];
        if ($c === '<' || $c === '{' || $c === '(' || $c === '[') {
            $depth++;
        } elseif ($c === '>' || $c === '}' || $c === ')' || $c === ']') {
            if ($c === '>' && $i > 0 && ($s[$i - 1] === '=' || $s[$i - 1] === '-')) {
                continue;
            }
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }
    return null;
}

/**
 * @return list<string>
 */
function split_top_level(string $s, string $sep): array
{
    $parts = [];
    $depth = 0;
    $cur = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($c === '<' || $c === '{' || $c === '(' || $c === '[') {
            $depth++;
        } elseif ($c === '>' || $c === '}' || $c === ')' || $c === ']') {
            if (!($c === '>' && $i > 0 && ($s[$i - 1] === '=' || $s[$i - 1] === '-'))) {
                $depth--;
            }
        }
        if ($c === $sep && $depth === 0) {
            $parts[] = $cur;
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    $parts[] = $cur;
    return $parts;
}

/**
 * Normalize every whitespace-delimited token that contains '|'.
 * Unbalanced leading openers and trailing closers/punctuation are kept outside.
 */
function normalize_union_order(string $line): string
{
    return (string)preg_replace_callback('/\S*\|\S*/', static function (array $m): string {
        $tok = $m[0];
        $prefix = '';
        $suffix = '';
        while ($tok !== '') {
            $c = $tok[0];
            $quote = $c === '"' || $c === "'";
            if (($quote && substr_count($tok, $c) % 2 === 1)
                || ($c === '(' && substr_count($tok, '(') > substr_count($tok, ')'))
                || ($c === '[' && substr_count($tok, '[') > substr_count($tok, ']'))) {
                $prefix .= $c;
                $tok = substr($tok, 1);
                continue;
            }
            break;
        }
        while ($tok !== '') {
            $c = $tok[strlen($tok) - 1];
            $quote = $c === '"' || $c === "'";
            if (in_array($c, [',', '.', ';', ':'], true)
                || ($quote && substr_count($tok, $c) % 2 === 1)
                || ($c === ')' && substr_count($tok, ')') > substr_count($tok, '('))
                || ($c === ']' && substr_count($tok, ']') > substr_count($tok, '['))) {
                $suffix = $c . $suffix;
                $tok = substr($tok, 0, -1);
                continue;
            }
            break;
        }
        // matching quotes around the whole type
        while (strlen($tok) >= 2 && ($tok[0] === '"' || $tok[0] === "'") && $tok[strlen($tok) - 1] === $tok[0]) {
            $prefix .= $tok[0];
            $suffix = $tok[0] . $suffix;
            $tok = substr($tok, 1, -1);
        }
        return $prefix . normalize_type($tok) . $suffix;
    }, $line);
}

/**
 * @param list<string> $a
 * @param list<string> $b
 */
function multisets_equal(array $a, array $b): bool
{
    sort($a, SORT_STRING);
    sort($b, SORT_STRING);
    return $a === $b;
}

/**
 * @param list<string> $a lines only in A for this group
 * @param list<string> $b lines only in B for this group
 */
function classify(array $a, array $b): string
{
    if (count($a) !== count($b)) {
        return CLASS_REAL;
    }
    $sa = array_map('issue_strip_suggestion', $a);
    $sb = array_map('issue_strip_suggestion', $b);
    if (multisets_equal($sa, $sb)) {
        return CLASS_SUGGESTION;
    }
    if (multisets_equal(array_map('normalize_union_order', $sa), array_map('normalize_union_order', $sb))) {
        return CLASS_UNION;
    }
    return CLASS_REAL;
}

function main(array $argv): int
{
    $files = [];
    $examples = 20;
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--examples') {
            $examples = (int)($argv[++$i] ?? 20);
        } elseif (strncmp($arg, '--examples=', 11) === 0) {
            $examples = (int)substr($arg, 11);
        } elseif ($arg === '-h' || $arg === '--help') {
            fwrite(STDOUT, "Usage: issue_diff.php A.txt B.txt [--examples N]\n");
            return 0;
        } else {
            $files[] = $arg;
        }
    }
    if (count($files) !== 2) {
        fwrite(STDERR, "Usage: issue_diff.php A.txt B.txt [--examples N]\n");
        return 3;
    }
    ini_set('memory_limit', '-1');
    $a = load_issue_lines($files[0]);
    $b = load_issue_lines($files[1]);
    if ($a === $b) {
        printf("IDENTICAL %d %s\n", count($a), issue_lines_sha1($a));
        return 0;
    }

    // Multiset difference (both lists are sorted with strcmp).
    $onlyA = [];
    $onlyB = [];
    $i = $j = 0;
    $na = count($a);
    $nb = count($b);
    while ($i < $na || $j < $nb) {
        if ($j >= $nb) {
            $onlyA[] = $a[$i++];
        } elseif ($i >= $na) {
            $onlyB[] = $b[$j++];
        } else {
            $cmp = strcmp($a[$i], $b[$j]);
            if ($cmp === 0) {
                $i++;
                $j++;
            } elseif ($cmp < 0) {
                $onlyA[] = $a[$i++];
            } else {
                $onlyB[] = $b[$j++];
            }
        }
    }

    /** @var array<string,array{type:string,a:list<string>,b:list<string>}> $groups */
    $groups = [];
    foreach (['a' => $onlyA, 'b' => $onlyB] as $side => $lines) {
        foreach ($lines as $line) {
            [$key, $type] = group_key($line);
            $groups[$key] ??= ['type' => $type, 'a' => [], 'b' => []];
            $groups[$key][$side][] = $line;
        }
    }
    ksort($groups, SORT_STRING);

    $classCounts = [CLASS_REAL => 0, CLASS_SUGGESTION => 0, CLASS_UNION => 0];
    $byType = [];
    $byClass = [CLASS_REAL => [], CLASS_SUGGESTION => [], CLASS_UNION => []];
    foreach ($groups as $key => $g) {
        $class = classify($g['a'], $g['b']);
        $classCounts[$class]++;
        $byType[$g['type']] ??= [CLASS_REAL => 0, CLASS_SUGGESTION => 0, CLASS_UNION => 0, 'a' => 0, 'b' => 0];
        $byType[$g['type']][$class]++;
        $byType[$g['type']]['a'] += count($g['a']);
        $byType[$g['type']]['b'] += count($g['b']);
        $byClass[$class][$key] = $g;
    }

    printf(
        "DIFFERENT a=%d (%s) b=%d (%s) only_a=%d only_b=%d groups=%d\n",
        $na,
        issue_lines_sha1($a),
        $nb,
        issue_lines_sha1($b),
        count($onlyA),
        count($onlyB),
        count($groups)
    );
    printf(
        "groups: real=%d suggestion-only=%d union-order-only=%d\n\n",
        $classCounts[CLASS_REAL],
        $classCounts[CLASS_SUGGESTION],
        $classCounts[CLASS_UNION]
    );

    uksort($byType, static function (string $x, string $y) use ($byType): int {
        $tx = $byType[$x][CLASS_REAL] + $byType[$x][CLASS_SUGGESTION] + $byType[$x][CLASS_UNION];
        $ty = $byType[$y][CLASS_REAL] + $byType[$y][CLASS_SUGGESTION] + $byType[$y][CLASS_UNION];
        return [$byType[$y][CLASS_REAL], $ty, $x] <=> [$byType[$x][CLASS_REAL], $tx, $y];
    });
    printf("%-48s %6s %6s %6s %7s %7s\n", 'issue type', 'real', 'sugg', 'union', '-A', '+B');
    foreach ($byType as $type => $c) {
        printf(
            "%-48s %6d %6d %6d %7d %7d\n",
            $type,
            $c[CLASS_REAL],
            $c[CLASS_SUGGESTION],
            $c[CLASS_UNION],
            $c['a'],
            $c['b']
        );
    }

    foreach ([CLASS_REAL, CLASS_SUGGESTION, CLASS_UNION] as $class) {
        if (!$byClass[$class] || $examples <= 0) {
            continue;
        }
        printf("\nExamples (%s, %d of %d groups):\n", $class, min($examples, count($byClass[$class])), count($byClass[$class]));
        $n = 0;
        foreach ($byClass[$class] as $key => $g) {
            if ($n++ >= $examples) {
                break;
            }
            echo "  [$key]\n";
            foreach ($g['a'] as $line) {
                echo "  - $line\n";
            }
            foreach ($g['b'] as $line) {
                echo "  + $line\n";
            }
        }
    }

    return $classCounts[CLASS_REAL] > 0 || $classCounts[CLASS_UNION] > 0 ? 1 : 2;
}

exit(main($argv));
