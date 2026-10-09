#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Summarize a bench.sh result directory.
 *
 * Usage:
 *   bench_summarize.php <run dir> [--no-tsv] [--quiet]
 *   bench_summarize.php --compare <run dir A> <run dir B>
 *
 * Reads <run dir>/env.json and every <run dir>/run-<k>/ (time.json, mem.json,
 * result.json, issues.txt, optional timings.json), writes <run dir>/summary.json,
 * appends one row to $PHAN_PERF_HOME/results/results.tsv and prints a short report.
 *
 * Status: OK, FAILED (some run failed; stats use the passing runs),
 * NONDETERMINISTIC (passing runs disagree on the sorted issue list; at -j>1 the
 * comparison ignores the trailing " (<suggestion>)" of each line, because worker
 * result order makes suggestions vary between runs), NO_RUNS.
 *
 * --compare prints the median deltas of B relative to A and applies the noise
 * rule: a change counts only if |delta wall| > max(2 * max(MAD_A, MAD_B), 1% of A)
 * and user CPU moves the same way, with identical issue sha1.
 */

const METRICS = ['wall_s', 'user_s', 'sys_s', 'maxrss_kb', 'peak_anon_kb', 'peak_current_kb'];
const TSV_COLUMNS = [
    'utc', 'phan_sha', 'dirty', 'target', 'variant', 'j', 'runs', 'wall_s', 'user_s', 'sys_s',
    'maxrss_kb', 'peak_anon_kb', 'parse_s', 'classes_s', 'functions_s', 'methods_s', 'analyze_s',
    'issues', 'issues_sha1', 'load1', 'status', 'label', 'dir',
];

/**
 * @return ?array<string,mixed>
 */
function read_json(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/**
 * GNU time writes "Command exited with non-zero status N" / "Command terminated
 * by signal N" before the -f output; keep the last line that looks like JSON.
 *
 * @return ?array<string,mixed>
 */
function read_time_json(string $runDir): ?array
{
    foreach (['time.json', 'time.raw'] as $name) {
        $path = "$runDir/$name";
        if (!is_file($path)) {
            continue;
        }
        $json = null;
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (isset($line[0]) && $line[0] === '{') {
                $json = $line;
            }
        }
        if ($json !== null) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }
    return null;
}

/**
 * Same as issue_diff.php: strip the trailing " (<suggestion>)" PlainTextPrinter appends.
 */
function strip_suggestion(string $line): string
{
    $end = strlen($line) - 1;
    if ($end < 1 || $line[$end] !== ')') {
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

/**
 * @param list<string> $lines
 */
function lines_sha1(array $lines): string
{
    sort($lines, SORT_STRING);
    return sha1($lines ? implode("\n", $lines) . "\n" : '');
}

/**
 * Same normalization as issue_diff.php.
 * @return array{0:int,1:string,2:string} [count, sha1, sha1 with suggestions stripped]
 */
function issues_digest(string $path): array
{
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        return [0, '', ''];
    }
    $lines = [];
    while (($line = fgets($fp)) !== false) {
        $line = rtrim((string)preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $line));
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    fclose($fp);
    return [count($lines), lines_sha1($lines), lines_sha1(array_map('strip_suggestion', $lines))];
}

/**
 * phases: list of {name, wall_s, ...} (schema phan-phase-timings/1); also accepts
 * a {name: {wall_s}} map and wall/wall_ms/wall_ns keys.
 *
 * @param array<string,mixed> $timings
 * @return array<string,float>
 */
function phase_walls(array $timings): array
{
    $phases = $timings['phases'] ?? [];
    if (!is_array($phases)) {
        return [];
    }
    $out = [];
    foreach ($phases as $key => $p) {
        if (!is_array($p)) {
            if (is_numeric($p) && is_string($key)) {
                $out[$key] = (float)$p;
            }
            continue;
        }
        $name = $p['name'] ?? $p['phase'] ?? (is_string($key) ? $key : null);
        if (!is_string($name)) {
            continue;
        }
        $wall = null;
        if (isset($p['wall_s'])) {
            $wall = (float)$p['wall_s'];
        } elseif (isset($p['wall'])) {
            $wall = (float)$p['wall'];
        } elseif (isset($p['wall_ms'])) {
            $wall = $p['wall_ms'] / 1000.0;
        } elseif (isset($p['wall_ns'])) {
            $wall = $p['wall_ns'] / 1e9;
        }
        if ($wall !== null) {
            $out[$name] = ($out[$name] ?? 0.0) + $wall;
        }
    }
    return $out;
}

/**
 * @param list<float|int> $values
 */
function median(array $values): ?float
{
    $n = count($values);
    if ($n === 0) {
        return null;
    }
    sort($values);
    $mid = intdiv($n, 2);
    return $n % 2 ? (float)$values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2.0;
}

/**
 * @param list<float|int> $values
 * @return ?array{median:float,min:float,max:float,mad:float,n:int,values:list<float|int>}
 */
function stats(array $values): ?array
{
    $values = array_values(array_filter($values, static fn($v): bool => $v !== null));
    if (!$values) {
        return null;
    }
    $med = (float)median($values);
    $dev = array_map(static fn($v): float => abs($v - $med), $values);
    return [
        'median' => round($med, 6),
        'min' => (float)min($values),
        'max' => (float)max($values),
        'mad' => round((float)median($dev), 6),
        'n' => count($values),
        'values' => $values,
    ];
}

/**
 * @return list<string>
 */
function run_dirs(string $dir): array
{
    $runs = [];
    foreach (glob("$dir/run-*", GLOB_ONLYDIR) ?: [] as $d) {
        if (preg_match('/run-(\d+)$/', $d, $m)) {
            $runs[(int)$m[1]] = $d;
        }
    }
    ksort($runs);
    return array_values($runs);
}

function fmt($v, int $dec = 2): string
{
    if ($v === null) {
        return '-';
    }
    if (!is_float($v)) {
        return (string)$v;
    }
    return $dec === 0 ? sprintf('%.0f', $v) : number_format($v, $dec, '.', '');
}

/**
 * @return array<string,mixed>
 */
function summarize(string $dir): array
{
    $env = read_json("$dir/env.json") ?? [];
    $perRun = [];
    $failed = [];
    foreach (run_dirs($dir) as $runDir) {
        $k = (int)substr((string)strrchr($runDir, '-'), 1);
        $time = read_time_json($runDir);
        $mem = read_json("$runDir/mem.json") ?? [];
        $result = read_json("$runDir/result.json");
        $timings = read_json("$runDir/timings.json");
        [$nIssues, $sha, $canon] = is_file("$runDir/issues.txt") ? issues_digest("$runDir/issues.txt") : [0, '', ''];
        $ok = $result['ok'] ?? ($time !== null && ($time['exit'] ?? 1) === 0);
        $reasons = $result['reasons'] ?? ($time === null ? ['missing time.json'] : []);
        if ($sha === '' && $ok) {
            $ok = false;
            $reasons[] = 'missing issues.txt';
        }
        $row = [
            'run' => $k,
            'ok' => (bool)$ok,
            'reasons' => $reasons,
            'wall_s' => $time['wall_s'] ?? null,
            'user_s' => $time['user_s'] ?? null,
            'sys_s' => $time['sys_s'] ?? null,
            'maxrss_kb' => $time['maxrss_kb'] ?? null,
            'peak_anon_kb' => $mem['peak_anon_kb'] ?? null,
            'peak_current_kb' => $mem['peak_current_kb'] ?? null,
            'oom_kill' => $mem['oom_kill'] ?? null,
            'issues' => $nIssues,
            'issues_sha1' => $sha,
            'issues_canon_sha1' => $canon,
            'phases' => $timings !== null ? phase_walls($timings) : null,
            'totals' => $timings['totals'] ?? null,
            'load1' => $result['load1'] ?? null,
        ];
        $perRun[] = $row;
        if (!$row['ok']) {
            $failed[] = ['run' => $k, 'reasons' => $reasons];
        }
    }
    $okRuns = array_values(array_filter($perRun, static fn(array $r): bool => $r['ok']));

    $metrics = [];
    foreach (METRICS as $m) {
        $metrics[$m] = stats(array_column($okRuns, $m));
    }

    $phaseOrder = [];
    $phaseValues = [];
    foreach ($okRuns as $r) {
        foreach ($r['phases'] ?? [] as $name => $wall) {
            if (!isset($phaseValues[$name])) {
                $phaseOrder[] = $name;
            }
            $phaseValues[$name][] = $wall;
        }
    }
    $phases = [];
    foreach ($phaseOrder as $name) {
        $phases[$name] = stats($phaseValues[$name]);
    }
    $totals = [];
    foreach (['serial_fraction', 'imbalance_s', 'efficiency'] as $t) {
        $vals = [];
        foreach ($okRuns as $r) {
            if (is_array($r['totals']) && isset($r['totals'][$t]) && is_numeric($r['totals'][$t])) {
                $vals[] = (float)$r['totals'][$t];
            }
        }
        if ($vals) {
            $totals[$t] = round((float)median($vals), 6);
        }
    }

    // Run-to-run determinism: exact at -j1; at -j>1 suggestions are known to vary
    // with worker result arrival order (see README), so compare suggestion-stripped.
    $j = (int)($env['j'] ?? 1);
    $shas = array_values(array_unique(array_column($okRuns, 'issues_sha1')));
    $canons = array_values(array_unique(array_column($okRuns, 'issues_canon_sha1')));
    $compared = $j > 1 ? $canons : $shas;
    if (!$perRun) {
        $status = 'NO_RUNS';
    } elseif ($failed) {
        $status = 'FAILED';
    } elseif (count($compared) > 1) {
        $status = 'NONDETERMINISTIC';
    } else {
        $status = 'OK';
    }

    return [
        'schema' => 'phan-perf-summary/1',
        'dir' => realpath($dir) ?: $dir,
        'utc' => $env['utc'] ?? null,
        'target' => $env['target'] ?? null,
        'variant' => $env['variant'] ?? null,
        'j' => $env['j'] ?? null,
        'label' => $env['label'] ?? '',
        'phan_sha' => $env['phan']['sha'] ?? null,
        'dirty' => $env['phan']['dirty'] ?? null,
        'load1' => $env['load1'] ?? null,
        'perf_home' => $env['perf_home'] ?? null,
        'status' => $status,
        'runs_total' => count($perRun),
        'runs_ok' => count($okRuns),
        'failed_runs' => $failed,
        'issues' => $okRuns[0]['issues'] ?? null,
        'issues_sha1' => count($shas) === 1 ? $shas[0] : null,
        'issues_canon_sha1' => count($canons) === 1 ? $canons[0] : null,
        'determinism_check' => $j > 1 ? 'suggestion-stripped' : 'exact',
        'raw_sha1_varies' => count($shas) > 1,
        'issues_sha1_by_run' => array_column($perRun, 'issues_sha1', 'run'),
        'issues_canon_sha1_by_run' => array_column($perRun, 'issues_canon_sha1', 'run'),
        'metrics' => $metrics,
        'phases' => $phases,
        'totals' => $totals,
        'runs' => $perRun,
    ];
}

function phase_median(array $s, string ...$names): ?float
{
    foreach ($names as $n) {
        if (isset($s['phases'][$n]['median'])) {
            return $s['phases'][$n]['median'];
        }
    }
    return null;
}

function append_tsv(array $s): string
{
    $perf = getenv('PHAN_PERF_HOME') ?: ($s['perf_home'] ?: (getenv('HOME') . '/phan-perf'));
    $tsv = "$perf/results/results.tsv";
    if (!is_dir(dirname($tsv))) {
        mkdir(dirname($tsv), 0777, true);
    }
    $j = (int)($s['j'] ?? 1);
    $m = static fn(string $k) => $s['metrics'][$k]['median'] ?? null;
    $row = [
        $s['utc'], $s['phan_sha'], $s['dirty'] ?: '-', $s['target'], $s['variant'], $s['j'], $s['runs_ok'],
        $m('wall_s'), $m('user_s'), $m('sys_s'), $m('maxrss_kb'), $m('peak_anon_kb'),
        phase_median($s, 'parse'), phase_median($s, 'analyze_classes'),
        phase_median($s, 'analyze_functions'), phase_median($s, 'analyze_methods'),
        $j > 1 ? phase_median($s, 'analyze_wait', 'analyze') : phase_median($s, 'analyze', 'analyze_wait'),
        $s['issues'],
        $s['issues_sha1'] ?? ($s['issues_canon_sha1'] !== null ? 'canon:' . $s['issues_canon_sha1'] : null),
        $s['load1'], $s['status'], $s['label'] ?: '-', $s['dir'],
    ];
    foreach ($row as $i => $v) {
        $dec = str_ends_with(TSV_COLUMNS[$i], '_kb') ? 0 : 3;
        $row[$i] = $v === null ? '' : str_replace(["\t", "\n"], ' ', is_float($v) ? fmt($v, $dec) : (string)$v);
    }
    $new = !is_file($tsv) || filesize($tsv) === 0;
    $fp = fopen($tsv, 'ab');
    if ($fp === false) {
        fwrite(STDERR, "bench_summarize: cannot append to $tsv\n");
        return $tsv;
    }
    flock($fp, LOCK_EX);
    if ($new) {
        fwrite($fp, implode("\t", TSV_COLUMNS) . "\n");
    }
    fwrite($fp, implode("\t", $row) . "\n");
    flock($fp, LOCK_UN);
    fclose($fp);
    return $tsv;
}

function print_summary(array $s): void
{
    printf(
        "%s  -j%s  %s%s  phan %s%s  runs %d/%d  status %s\n",
        $s['target'],
        $s['j'],
        $s['variant'],
        $s['label'] ? "  [{$s['label']}]" : '',
        substr((string)$s['phan_sha'], 0, 10),
        $s['dirty'] ? "-dirty-{$s['dirty']}" : '',
        $s['runs_ok'],
        $s['runs_total'],
        $s['status']
    );
    printf(
        "issues %s  sha1 %s  load1 %s\n",
        fmt($s['issues']),
        $s['issues_sha1'] ?? ($s['runs_ok'] ? '(differs per run)' : '(no passing runs)'),
        fmt($s['load1'])
    );
    foreach ($s['failed_runs'] as $f) {
        printf("  run-%d FAILED: %s\n", $f['run'], implode('; ', $f['reasons']));
    }
    if ($s['status'] === 'NONDETERMINISTIC' || $s['raw_sha1_varies']) {
        if ($s['status'] !== 'NONDETERMINISTIC') {
            printf(
                "  note: raw issue sha1 varies across runs only in suggestions (known -j>1 nondeterminism); suggestion-stripped sha1 %s\n",
                $s['issues_canon_sha1']
            );
        }
        foreach ($s['issues_sha1_by_run'] as $k => $sha) {
            printf("  run-%d issues sha1 %s  stripped %s\n", $k, $sha, $s['issues_canon_sha1_by_run'][$k] ?? '-');
        }
    }
    if (!$s['runs_ok']) {
        return;
    }
    printf("%-16s %12s %12s %12s %10s %6s\n", 'metric', 'median', 'min', 'max', 'MAD', 'MAD%');
    foreach ($s['metrics'] as $name => $st) {
        if ($st === null) {
            continue;
        }
        $dec = str_ends_with($name, '_kb') ? 0 : 3;
        printf(
            "%-16s %12s %12s %12s %10s %5.1f%%\n",
            $name,
            fmt($st['median'], $dec),
            fmt($st['min'], $dec),
            fmt($st['max'], $dec),
            fmt($st['mad'], $dec),
            $st['median'] != 0 ? 100 * $st['mad'] / $st['median'] : 0
        );
    }
    if ($s['phases']) {
        echo "phase wall_s (median):\n";
        $cells = [];
        foreach ($s['phases'] as $name => $st) {
            if ($st !== null && $st['median'] >= 0.005) {
                $cells[] = sprintf('%s %.3f', $name, $st['median']);
            }
        }
        echo '  ' . wordwrap(implode('  ', $cells), 100, "\n  ") . "\n";
        foreach ($s['totals'] as $k => $v) {
            printf("  %s %.3f", $k, $v);
        }
        echo $s['totals'] ? "\n" : '';
    }
    $w = $s['metrics']['wall_s'] ?? null;
    if ($w !== null) {
        printf("noise threshold max(2*MAD, 1%%) on wall: %.3f s\n", max(2 * $w['mad'], 0.01 * $w['median']));
    }
}

function compare(string $dirA, string $dirB): int
{
    $a = read_json("$dirA/summary.json") ?? summarize($dirA);
    $b = read_json("$dirB/summary.json") ?? summarize($dirB);
    printf("A: %s  %s%s\n", $a['dir'], substr((string)$a['phan_sha'], 0, 10), $a['dirty'] ? '-dirty-' . $a['dirty'] : '');
    printf("B: %s  %s%s\n", $b['dir'], substr((string)$b['phan_sha'], 0, 10), $b['dirty'] ? '-dirty-' . $b['dirty'] : '');
    printf("%-16s %12s %12s %12s %8s %10s\n", 'metric', 'A median', 'B median', 'B-A', 'B-A %', 'threshold');
    $verdict = [];
    foreach (METRICS as $m) {
        $sa = $a['metrics'][$m] ?? null;
        $sb = $b['metrics'][$m] ?? null;
        if ($sa === null || $sb === null) {
            continue;
        }
        $delta = $sb['median'] - $sa['median'];
        $thr = max(2 * max($sa['mad'], $sb['mad']), 0.01 * $sa['median']);
        $pct = $sa['median'] != 0 ? 100 * $delta / $sa['median'] : 0;
        $dec = str_ends_with($m, '_kb') ? 0 : 3;
        printf(
            "%-16s %12s %12s %12s %7.2f%% %10s%s\n",
            $m,
            fmt($sa['median'], $dec),
            fmt($sb['median'], $dec),
            fmt($delta, $dec),
            $pct,
            fmt($thr, $dec),
            abs($delta) > $thr ? '  *' : ''
        );
        $verdict[$m] = abs($delta) > $thr ? ($delta < 0 ? -1 : 1) : 0;
    }
    $sameIssues = $a['issues_sha1'] !== null && $a['issues_sha1'] === $b['issues_sha1'];
    $sameCanon = ($a['issues_canon_sha1'] ?? null) !== null && $a['issues_canon_sha1'] === ($b['issues_canon_sha1'] ?? null);
    printf(
        "issues: A %s %s / B %s %s => %s\n",
        fmt($a['issues']),
        $a['issues_sha1'] ?? '-',
        fmt($b['issues']),
        $b['issues_sha1'] ?? '-',
        $sameIssues ? 'IDENTICAL' : ($sameCanon ? 'IDENTICAL after stripping suggestions' : 'DIFFERENT')
    );
    if (!$sameIssues && $sameCanon && (int)($a['j'] ?? 1) > 1) {
        $sameIssues = true; // -j>1 gate: suggestion-only differences are tolerated
    }
    $wall = $verdict['wall_s'] ?? 0;
    $user = $verdict['user_s'] ?? 0;
    if ($wall !== 0 && $wall === $user && $sameIssues) {
        echo $wall < 0 ? "verdict: B is faster (beats noise, user CPU agrees, issues identical)\n"
            : "verdict: B is slower (beats noise, user CPU agrees, issues identical)\n";
    } elseif ($wall !== 0 && $sameIssues) {
        echo "verdict: wall changed beyond noise but user CPU does not agree; inconclusive\n";
    } elseif (!$sameIssues) {
        echo "verdict: issue output differs; run issue_diff.php before trusting timings\n";
    } else {
        echo "verdict: no difference beyond noise\n";
    }
    return 0;
}

function main(array $argv): int
{
    ini_set('memory_limit', '-1');
    $args = array_slice($argv, 1);
    if (($args[0] ?? '') === '--compare') {
        if (count($args) !== 3) {
            fwrite(STDERR, "Usage: bench_summarize.php --compare <run dir A> <run dir B>\n");
            return 3;
        }
        return compare($args[1], $args[2]);
    }
    $noTsv = in_array('--no-tsv', $args, true);
    $quiet = in_array('--quiet', $args, true);
    $dirs = array_values(array_filter($args, static fn(string $a): bool => $a === '' || $a[0] !== '-'));
    if (count($dirs) !== 1 || !is_dir($dirs[0])) {
        fwrite(STDERR, "Usage: bench_summarize.php <run dir> [--no-tsv] [--quiet]\n       bench_summarize.php --compare <run dir A> <run dir B>\n");
        return 3;
    }
    $dir = rtrim($dirs[0], '/');
    $s = summarize($dir);
    file_put_contents("$dir/summary.json", json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $tsv = $noTsv ? null : append_tsv($s);
    if (!$quiet) {
        print_summary($s);
        echo "summary: $dir/summary.json" . ($tsv ? "\nresults: $tsv" : '') . "\n";
    }
    return $s['status'] === 'OK' ? 0 : 1;
}

exit(main($argv));
