<?php

declare(strict_types=1);

/**
 * Issue-output normalization shared by issue_diff.php and bench_summarize.php.
 */

/**
 * Normalized issue lines of a Phan `-m text` output file: ANSI escapes stripped, rtrim,
 * empty lines dropped, duplicates kept, sorted with strcmp. Null if unreadable.
 *
 * @return ?list<string>
 */
function issue_load_lines(string $path): ?array
{
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        return null;
    }
    $lines = [];
    while (($line = fgets($fp)) !== false) {
        $line = rtrim((string)preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $line));
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    fclose($fp);
    sort($lines, SORT_STRING);
    return $lines;
}

/**
 * sha1(implode("\n", sorted) . "\n"), or sha1('') for no lines.
 *
 * @param list<string> $lines
 */
function issue_lines_sha1(array $lines): string
{
    sort($lines, SORT_STRING);
    return sha1($lines ? implode("\n", $lines) . "\n" : '');
}

/**
 * Remove the suggestion PlainTextPrinter appends after the message.
 *
 * The printer writes "<file>:<line> <type> <message>", then " (at column N)" when columns
 * are shown, then " (<suggestion>)" when there is one. A line ending in
 * " (at column N)" has no suggestion and is returned unchanged; otherwise the last
 * balanced parenthetical preceded by a space is removed (once). A message that itself
 * ends in " (...)" cannot be told apart from a suggestion; that only matters when two
 * lines differ in nothing else.
 */
function issue_strip_suggestion(string $line): string
{
    $end = strlen($line) - 1;
    if ($end < 1 || $line[$end] !== ')' || preg_match('/ \(at column \d+\)$/', $line)) {
        return $line;
    }
    $depth = 0;
    for ($i = $end; $i >= 0; $i--) {
        $c = $line[$i];
        if ($c === ')') {
            $depth++;
        } elseif ($c === '(') {
            if (--$depth === 0) {
                return $i > 0 && $line[$i - 1] === ' ' ? substr($line, 0, $i - 1) : $line;
            }
        }
    }
    return $line;
}
