#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Aggregate phpspy samples into top-N self / inclusive tables.
 *
 * Usage:
 *   phpspy_top.php [--top N] [--by func|class|file|ns] [--phase P] [--by-phase]
 *                  [--min-pct X] [--tsv] A [--diff B]
 *
 * Input formats (auto-detected per line, may be gzip-compressed with a .gz suffix):
 *   - phpspy default multi-line traces: one frame per line "<depth> <func> <file>:<line>",
 *     samples separated by a blank line or "# - - - - -"; "# glopeek globals.__phase = P"
 *     lines tag the sample with a phase (phpspy -g globals.__phase)
 *   - phpspy -1 (single-line): the same frames separated by tabs, one sample per line
 *   - folded stacks (stackcollapse-phpspy.pl output): "root;...;leaf <count>"
 *
 * self      = samples whose leaf frame (depth 0) maps to the key
 * inclusive = samples where the key appears anywhere in the stack (counted once)
 * --by-phase  prints the share of samples per phase instead of the tables
 * --diff B    prints the self% (and inclusive%) change from A to B per key, by |delta|
 */

final class Profile
{
    public int $samples = 0;
    public int $matched = 0;
    /** @var array<string,int> */
    public array $self = [];
    /** @var array<string,int> */
    public array $incl = [];
    /** @var array<string,int> */
    public array $phases = [];
    public bool $sawPhase = false;
    public int $badLines = 0;

    public function __construct(
        private string $by,
        private ?string $phaseFilter
    ) {
    }

    /**
     * @param list<array{0:string,1:string}> $frames [func, file] leaf first
     */
    public function add(array $frames, ?string $phase, int $weight = 1): void
    {
        if (!$frames) {
            return;
        }
        $this->samples += $weight;
        $p = $phase ?? '(none)';
        $this->phases[$p] = ($this->phases[$p] ?? 0) + $weight;
        if ($this->phaseFilter !== null && $phase !== $this->phaseFilter) {
            return;
        }
        $this->matched += $weight;
        $leaf = $this->key($frames[0]);
        $this->self[$leaf] = ($this->self[$leaf] ?? 0) + $weight;
        $seen = [];
        foreach ($frames as $f) {
            $k = $this->key($f);
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $this->incl[$k] = ($this->incl[$k] ?? 0) + $weight;
            }
        }
    }

    /**
     * @param array{0:string,1:string} $frame
     */
    private function key(array $frame): string
    {
        [$func, $file] = $frame;
        switch ($this->by) {
            case 'class':
                $pos = strpos($func, '::');
                return $pos === false ? $func : substr($func, 0, $pos);
            case 'file':
                return $file;
            case 'ns':
                $pos = strpos($func, '::');
                $name = $pos === false ? $func : substr($func, 0, $pos);
                $nsPos = strrpos($name, '\\');
                return $nsPos === false ? '\\' : substr($name, 0, $nsPos);
            default:
                return $func;
        }
    }
}

/**
 * @return array{0:string,1:string}|null [func, file]
 */
function parse_frame(string $line): ?array
{
    if (preg_match('/^\d+ (\S+) (.*):-?\d+$/', $line, $m)) {
        return [$m[1], $m[2]];
    }
    if (preg_match('/^\d+ (\S+)$/', $line, $m)) {
        return [$m[1], '?'];
    }
    return null;
}

function parse_meta(string $line, ?string &$phase, Profile $prof): void
{
    if (preg_match('/^# (?:glopeek|varpeek) (\S+) = (.*)$/', $line, $m) && str_ends_with(explode('@', $m[1])[0], '__phase')) {
        $v = trim($m[2]);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $phase = $v;
        $prof->sawPhase = true;
    }
}

function load_profile(string $path, string $by, ?string $phaseFilter): Profile
{
    $prof = new Profile($by, $phaseFilter);
    $fp = @fopen(str_ends_with($path, '.gz') ? "compress.zlib://$path" : $path, 'rb');
    if ($fp === false) {
        fwrite(STDERR, "phpspy_top: cannot open $path\n");
        exit(3);
    }
    $frames = [];
    $phase = null;
    while (($line = fgets($fp)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '' || str_starts_with($line, '# - ')) {
            $prof->add($frames, $phase);
            $frames = [];
            $phase = null;
            continue;
        }
        if (str_contains($line, "\t")) {
            // phpspy -1: one sample per line
            $prof->add($frames, $phase);
            $frames = [];
            $phase = null;
            foreach (explode("\t", $line) as $field) {
                if ($field === '') {
                    continue;
                }
                if ($field[0] === '#') {
                    parse_meta($field, $phase, $prof);
                } elseif (($f = parse_frame($field)) !== null) {
                    $frames[] = $f;
                }
            }
            $prof->add($frames, $phase);
            $frames = [];
            $phase = null;
            continue;
        }
        if ($line[0] === '#') {
            parse_meta($line, $phase, $prof);
            continue;
        }
        if (($f = parse_frame($line)) !== null && preg_match('/^\d+ /', $line) && !preg_match('/^[^ ]*;/', $line)) {
            if (preg_match('/^0 /', $line) && $frames) {
                // new sample without a separator
                $prof->add($frames, $phase);
                $frames = [];
                $phase = null;
            }
            $frames[] = $f;
            continue;
        }
        if (preg_match('/^(.*) (\d+)$/', $line, $m)) {
            // folded: root;...;leaf count
            $prof->add($frames, $phase);
            $frames = [];
            $phase = null;
            $stack = array_reverse(explode(';', $m[1]));
            $prof->add(array_map(static fn(string $fn): array => [$fn, '?'], $stack), null, (int)$m[2]);
            continue;
        }
        $prof->badLines++;
    }
    $prof->add($frames, $phase);
    fclose($fp);
    return $prof;
}

function pct(int $n, int $total): float
{
    return $total > 0 ? 100.0 * $n / $total : 0.0;
}

/**
 * @param array<string,int> $primary
 * @param array<string,int> $secondary
 */
function print_table(string $title, array $primary, array $secondary, int $total, int $top, float $minPct, bool $tsv, bool $primaryIsSelf): void
{
    arsort($primary);
    $section = $primaryIsSelf ? 'self' : 'incl';
    if (!$tsv) {
        printf("\n== %s ==\n%7s %8s %7s %8s  %s\n", $title, 'self%', 'self', 'incl%', 'incl', 'name');
    }
    $n = 0;
    foreach ($primary as $key => $count) {
        if ($n++ >= $top || pct($count, $total) < $minPct) {
            break;
        }
        $other = $secondary[$key] ?? 0;
        [$self, $incl] = $primaryIsSelf ? [$count, $other] : [$other, $count];
        if ($tsv) {
            printf("%s\t%s\t%d\t%.3f\t%d\t%.3f\n", $section, $key, $self, pct($self, $total), $incl, pct($incl, $total));
        } else {
            printf("%6.2f%% %8d %6.2f%% %8d  %s\n", pct($self, $total), $self, pct($incl, $total), $incl, $key);
        }
    }
}

function main(array $argv): int
{
    ini_set('memory_limit', '-1');
    $top = 30;
    $by = 'func';
    $phase = null;
    $byPhase = false;
    $minPct = 0.0;
    $tsv = false;
    $files = [];
    $diff = null;
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
                fwrite(STDERR, "phpspy_top: missing value for $a\n");
                exit(3);
            }
            return $args[++$i];
        };
        switch ($a) {
            case '--top': $top = max(1, (int)$val()); break;
            case '--by': $by = $val(); break;
            case '--phase': $phase = $val(); break;
            case '--by-phase': $byPhase = true; break;
            case '--min-pct': $minPct = (float)$val(); break;
            case '--tsv': $tsv = true; break;
            case '--diff': $diff = $val(); break;
            case '-h':
            case '--help':
                echo "Usage: phpspy_top.php [--top N] [--by func|class|file|ns] [--phase P] [--by-phase] [--min-pct X] [--tsv] A [--diff B]\n";
                return 0;
            default:
                if ($a !== '' && $a[0] === '-') {
                    fwrite(STDERR, "phpspy_top: unknown option $a\n");
                    return 3;
                }
                $files[] = $a;
        }
    }
    if (count($files) !== 1 || !in_array($by, ['func', 'class', 'file', 'ns'], true)) {
        fwrite(STDERR, "Usage: phpspy_top.php [--top N] [--by func|class|file|ns] [--phase P] [--by-phase] [--min-pct X] [--tsv] A [--diff B]\n");
        return 3;
    }

    $a = load_profile($files[0], $by, $phase);
    if ($a->badLines) {
        fprintf(STDERR, "phpspy_top: %s: ignored %d unparseable lines\n", $files[0], $a->badLines);
    }
    if ($phase !== null && !$a->sawPhase) {
        fprintf(STDERR, "phpspy_top: %s has no phase annotations (capture with phpspy -g globals.__phase on a Phan build that sets it); --phase %s matches nothing\n", $files[0], $phase);
    }

    if ($byPhase) {
        if (!$a->sawPhase) {
            fprintf(STDERR, "phpspy_top: %s has no phase annotations; all samples are reported as (none)\n", $files[0]);
        }
        arsort($a->phases);
        if (!$tsv) {
            printf("%s: %d samples\n%7s %8s  %s\n", $files[0], $a->samples, 'share', 'samples', 'phase');
        }
        foreach ($a->phases as $p => $count) {
            if ($tsv) {
                printf("phase\t%s\t%d\t%.3f\n", $p, $count, pct($count, $a->samples));
            } else {
                printf("%6.2f%% %8d  %s\n", pct($count, $a->samples), $count, $p);
            }
        }
        return 0;
    }

    if ($diff !== null) {
        $b = load_profile($diff, $by, $phase);
        $keys = array_keys($a->self + $b->self + $a->incl + $b->incl);
        $rows = [];
        foreach ($keys as $k) {
            $sa = pct($a->self[$k] ?? 0, $a->matched);
            $sb = pct($b->self[$k] ?? 0, $b->matched);
            $ia = pct($a->incl[$k] ?? 0, $a->matched);
            $ib = pct($b->incl[$k] ?? 0, $b->matched);
            $rows[$k] = [$sa, $sb, $sb - $sa, $ia, $ib, $ib - $ia];
        }
        uksort($rows, static fn($x, $y): int => [abs($rows[$y][2]), abs($rows[$y][5]), (string)$x] <=> [abs($rows[$x][2]), abs($rows[$x][5]), (string)$y]);
        if (!$tsv) {
            printf("A: %s (%d samples)  B: %s (%d samples)  by %s%s\n", $files[0], $a->matched, $diff, $b->matched, $by, $phase !== null ? "  phase $phase" : '');
            printf("%8s %8s %8s %8s %8s %8s  %s\n", 'A self%', 'B self%', 'Δself', 'A incl%', 'B incl%', 'Δincl', 'name');
        }
        $n = 0;
        foreach ($rows as $k => $r) {
            if ($n++ >= $top || abs($r[2]) < $minPct) {
                break;
            }
            if ($tsv) {
                printf("diff\t%s\t%.3f\t%.3f\t%.3f\t%.3f\t%.3f\t%.3f\n", $k, ...$r);
            } else {
                printf("%7.2f%% %7.2f%% %+7.2f %7.2f%% %7.2f%% %+7.2f  %s\n", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $k);
            }
        }
        return 0;
    }

    if (!$tsv) {
        printf(
            "%s: %d samples%s, by %s\n",
            $files[0],
            $a->matched,
            $phase !== null ? sprintf(' in phase %s (of %d)', $phase, $a->samples) : '',
            $by
        );
    }
    print_table("top $top by self", $a->self, $a->incl, $a->matched, $top, $minPct, $tsv, true);
    print_table("top $top by inclusive", $a->incl, $a->self, $a->matched, $top, $minPct, $tsv, false);
    return 0;
}

exit(main($argv));
