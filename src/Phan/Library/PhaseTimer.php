<?php

declare(strict_types=1);

namespace Phan\Library;

use Phan\CLI;
use Phan\Config;

/**
 * Collects wall clock, CPU and memory measurements for each phase of a Phan run.
 *
 * Enabled by `--dump-phase-timings` (text report on STDERR at exit) and/or
 * `--phase-timings-json <path>` (JSON report written at exit).
 * All state is static so that collection can start in the CLI constructor,
 * before the config file is read.
 *
 * When collection is disabled, every public method returns immediately.
 * Per-file call sites check `PhaseTimer::$enabled` before calling hrtime().
 *
 * In `--processes N` mode, each analysis worker resets the state inherited from the parent
 * (beginWorker()), and sends its measurements to the parent before exiting (finishWorker()).
 */
final class PhaseTimer
{
    /** Identifies the layout of the JSON report */
    public const SCHEMA = 'phan-phase-timings/1';

    /** recordFile() kind for Analysis::parseFile() */
    public const FILE_PARSE = 0;
    /** recordFile() kind for Analysis::analyzeFile() */
    public const FILE_ANALYZE = 1;

    private const TOP_FILE_COUNT = 25;
    private const TEXT_PREFIX = 'phan-timings';

    /** Snapshot fields that are summed as (end - start) for each phase */
    private const DELTA_FIELDS = ['ns', 'utime_us', 'stime_us', 'minflt', 'majflt', 'nvcsw', 'nivcsw'];
    /** Snapshot fields for which each phase keeps the largest value seen at the end of the phase */
    private const MAX_FIELDS = ['rss', 'maxrss_kb', 'zend_peak'];

    /** Fields read from /proc/self/smaps_rollup */
    private const SMAPS_FIELDS = ['Rss', 'Pss', 'Private_Clean', 'Private_Dirty', 'Shared_Clean', 'Shared_Dirty'];

    /** @var bool true if phase timings are being collected in this process */
    public static bool $enabled = false;

    /** @var bool true if the text report should be printed to STDERR by finish() */
    private static bool $dump_text = false;

    /** @var ?string the path from --phase-timings-json, if any */
    private static ?string $json_path = null;

    /** @var ?array<string,int> snapshot from when collection started in this process */
    private static ?array $start = null;

    /** Seconds between process start and enableFromCliOpts() */
    private static float $pre_start_s = 0.0;

    /** @var ?string the name of the phase being measured */
    private static ?string $current_phase = null;

    /** @var ?array<string,int> snapshot from the start of the current phase */
    private static ?array $current_start = null;

    /** @var array<string,array<string,int>> accumulated measurements by phase name, in order of first use */
    private static array $phases = [];

    /** @var array<int,array<string,int>> total nanoseconds spent on each file, by file kind */
    private static array $files = [[], []];

    /** @var array<string,int|float|string> */
    private static array $notes = [];

    /** @var ?int the index of this analysis worker, if this is a forked analysis worker */
    private static ?int $worker_id = null;

    /** @var int the number of files assigned to this analysis worker */
    private static int $worker_task_count = 0;

    /** @var array<int,array<string,mixed>> payloads sent by analysis workers, by worker index */
    private static array $workers = [];

    /** @var array<int,int> value of hrtime(true) when the parent reaped each analysis worker, by worker index */
    private static array $worker_exit_ns = [];

    /** @var ?bool true if /proc/self is available (computed on first use) */
    private static ?bool $has_proc = null;

    /** @var int the page size used to convert /proc/self/statm to bytes */
    private static int $page_size = 4096;

    /**
     * Enable collection if `--dump-phase-timings` or `--phase-timings-json` was passed.
     * This is called at the start of the CLI constructor, before the config file is read.
     *
     * @param array<string,string|list<mixed>|false> $opts
     */
    public static function enableFromCliOpts(array $opts): void
    {
        $dump_text = \array_key_exists('dump-phase-timings', $opts);
        $json_path = $opts['phase-timings-json'] ?? null;
        if (\is_array($json_path)) {
            // The last occurrence wins
            $json_path = $json_path ? $json_path[\count($json_path) - 1] : null;
        }
        if (!\is_string($json_path) || $json_path === '') {
            $json_path = null;
        }
        if (!$dump_text && $json_path === null) {
            return;
        }
        self::resetForTests();
        self::$enabled = true;
        self::$dump_text = $dump_text;
        self::$json_path = $json_path;
        $request_time = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        if (\is_float($request_time)) {
            self::$pre_start_s = \max(0.0, \microtime(true) - $request_time);
        }
        self::$start = self::snapshot();
    }

    /**
     * Discard all collected data and disable collection.
     * @internal used by unit tests and enableFromCliOpts()
     */
    public static function resetForTests(): void
    {
        self::$enabled = false;
        self::$dump_text = false;
        self::$json_path = null;
        self::$start = null;
        self::$pre_start_s = 0.0;
        self::$current_phase = null;
        self::$current_start = null;
        self::$phases = [];
        self::$files = [[], []];
        self::$notes = [];
        self::$worker_id = null;
        self::$worker_task_count = 0;
        self::$workers = [];
        self::$worker_exit_ns = [];
        unset($GLOBALS['__phase']);
    }

    /**
     * Close the current phase (if any) and start measuring $phase.
     * Measurements of phases with the same name are accumulated.
     *
     * This also sets `$GLOBALS['__phase']`, so that `phpspy -g globals.__phase` can attribute samples to phases.
     */
    public static function begin(string $phase): void
    {
        if (!self::$enabled) {
            return;
        }
        $snapshot = self::snapshot();
        self::closeCurrentPhase($snapshot);
        self::$current_phase = $phase;
        self::$current_start = $snapshot;
        $GLOBALS['__phase'] = $phase;
    }

    /**
     * Record the time spent parsing or analyzing a single file.
     *
     * @param int $kind self::FILE_PARSE or self::FILE_ANALYZE
     * @param int $ns elapsed nanoseconds, from hrtime(true)
     */
    public static function recordFile(int $kind, string $path, int $ns): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$files[$kind][$path] = (self::$files[$kind][$path] ?? 0) + $ns;
    }

    /**
     * Record a counter or other value to include in the report.
     */
    public static function note(string $key, int|float|string $value): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$notes[$key] = $value;
    }

    /**
     * Called in a forked analysis worker to discard the measurements inherited from the parent process.
     */
    public static function beginWorker(int $id, int $task_count): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$worker_id = $id;
        self::$worker_task_count = $task_count;
        self::$current_phase = null;
        self::$current_start = null;
        self::$phases = [];
        self::$files = [[], []];
        self::$notes = [];
        self::$workers = [];
        self::$worker_exit_ns = [];
        unset($GLOBALS['__phase']);
        if (\function_exists('memory_reset_peak_usage')) {
            \memory_reset_peak_usage();
        }
        self::$start = self::snapshot();
    }

    /**
     * Called in a forked analysis worker after it has finished analysis.
     *
     * @return array<string,mixed> the measurements of this worker, to be sent to the parent process and passed to recordWorker()
     */
    public static function finishWorker(): array
    {
        if (!self::$enabled) {
            return [];
        }
        $end = self::snapshot();
        self::closeCurrentPhase($end);
        return [
            'id' => self::$worker_id ?? -1,
            'task_count' => self::$worker_task_count,
            'phases' => self::$phases,
            'files' => self::$files[self::FILE_ANALYZE],
            'notes' => self::$notes,
            'memory_kb' => self::readMemoryKb(),
            'start_ns' => self::$start['ns'] ?? $end['ns'],
            'end_ns' => \hrtime(true),
        ];
    }

    /**
     * Called in the parent process with the measurements that worker $i sent from finishWorker()
     *
     * @param array<string,mixed> $payload
     */
    public static function recordWorker(int $i, array $payload): void
    {
        if (!self::$enabled) {
            return;
        }
        $files = $payload['files'] ?? null;
        if (\is_array($files)) {
            foreach ($files as $path => $ns) {
                self::$files[self::FILE_ANALYZE][$path] = (self::$files[self::FILE_ANALYZE][$path] ?? 0) + (int)$ns;
            }
        }
        unset($payload['files']);
        self::$workers[$i] = $payload;
    }

    /**
     * Called in the parent process when worker $i has been reaped.
     *
     * @param int $exit_ns the value of hrtime(true) when pcntl_waitpid() returned
     */
    public static function recordWorkerExit(int $i, int $exit_ns): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$worker_exit_ns[$i] = $exit_ns;
    }

    /**
     * Close the last phase and emit the requested reports.
     * Collection is disabled afterwards.
     */
    public static function finish(): void
    {
        if (!self::$enabled) {
            return;
        }
        self::closeCurrentPhase(self::snapshot());
        $report = self::buildReport();
        self::$enabled = false;

        if (self::$json_path !== null) {
            $path = Paths::toAbsolutePath(Config::getWorkingDirectory(), self::$json_path);
            if (\file_put_contents($path, self::encodeJson($report) . "\n") === false) {
                // @phan-suppress-next-line PhanPluginRemoveDebugCall
                \fwrite(\STDERR, "Could not write phase timings to '$path'\n");
            }
        }
        if (self::$dump_text) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            \fwrite(\STDERR, self::formatText($report));
        }
    }

    /**
     * Build the report from the data collected so far.
     *
     * @return array<string,mixed> (see self::SCHEMA)
     * @internal used by finish() and unit tests
     */
    public static function buildReport(): array
    {
        $end = self::snapshot();
        $start = self::$start ?? $end;
        $wall_s = ($end['ns'] - $start['ns']) / 1e9 + self::$pre_start_s;
        $self_usage = self::readRusage(0);
        $children_usage = self::readRusage(1);

        $phases = self::formatPhases(self::$phases);
        $analyze_wait_s = null;
        foreach ($phases as $phase) {
            if ($phase['name'] === 'analyze_wait') {
                $analyze_wait_s = $phase['wall_s'];
            }
        }

        $notes = self::$notes;
        $notes['pre_cli_s'] = \round(self::$pre_start_s, 6);
        foreach (self::readMemoryKb() as $key => $kb) {
            $notes[\strtolower($key) . '_mb'] = self::kbToMb($kb);
        }
        $notes['zend_peak_mb'] = self::bytesToMb($end['zend_peak']);
        $notes['minflt'] = $self_usage['minflt'];
        $notes['majflt'] = $self_usage['majflt'];
        $notes['nvcsw'] = $self_usage['nvcsw'];
        $notes['nivcsw'] = $self_usage['nivcsw'];

        $workers = [];
        $worker_count = 0;
        $max_worker_analyze_s = 0.0;
        $min_worker_analyze_s = \INF;
        $worker_analyze_cpu_s = 0.0;
        $worker_payloads = self::$workers;
        \ksort($worker_payloads);
        foreach ($worker_payloads as $i => $payload) {
            $id = (int)($payload['id'] ?? $i);
            $raw_phases = $payload['phases'] ?? [];
            $worker_phases = self::formatPhases(\is_array($raw_phases) ? $raw_phases : []);
            foreach ($worker_phases as $phase) {
                if ($phase['name'] === 'worker_analyze') {
                    $worker_count++;
                    $max_worker_analyze_s = \max($max_worker_analyze_s, $phase['wall_s']);
                    $min_worker_analyze_s = \min($min_worker_analyze_s, $phase['wall_s']);
                    $worker_analyze_cpu_s += $phase['user_s'] + $phase['sys_s'];
                }
            }
            $memory_kb = $payload['memory_kb'] ?? [];
            if (!\is_array($memory_kb)) {
                $memory_kb = [];
            }
            $end_ns = $payload['end_ns'] ?? null;
            $exit_ns = self::$worker_exit_ns[$i] ?? null;
            $workers[] = [
                'id' => $id,
                'files' => (int)($payload['task_count'] ?? 0),
                'phases' => $worker_phases,
                'private_dirty_mb' => isset($memory_kb['Private_Dirty']) ? self::kbToMb((int)$memory_kb['Private_Dirty']) : null,
                'pss_mb' => isset($memory_kb['Pss']) ? self::kbToMb((int)$memory_kb['Pss']) : null,
                'teardown_gap_s' => \is_int($end_ns) && \is_int($exit_ns) ? \round(($exit_ns - $end_ns) / 1e9, 6) : null,
            ];
            foreach (['Rss', 'VmHWM'] as $key) {
                if (isset($memory_kb[$key])) {
                    $notes["worker{$id}_" . \strtolower($key) . '_mb'] = self::kbToMb((int)$memory_kb[$key]);
                }
            }
            $minflt = 0;
            $majflt = 0;
            foreach (\is_array($raw_phases) ? $raw_phases : [] as $raw_phase) {
                $minflt += (int)($raw_phase['minflt'] ?? 0);
                $majflt += (int)($raw_phase['majflt'] ?? 0);
            }
            $notes["worker{$id}_minflt"] = $minflt;
            $notes["worker{$id}_majflt"] = $majflt;
            $worker_notes = $payload['notes'] ?? [];
            foreach (\is_array($worker_notes) ? $worker_notes : [] as $key => $value) {
                if (\is_int($value) || \is_float($value) || \is_string($value)) {
                    $notes["worker{$id}_$key"] = $value;
                }
            }
        }
        if ($workers) {
            $notes['worker_count'] = \count($workers);
        }

        return [
            'schema' => self::SCHEMA,
            'phan_version' => CLI::PHAN_VERSION,
            'php_version' => \PHP_VERSION,
            'opcache' => self::getOpcacheSettings(),
            'extensions' => [
                'ast' => self::getExtensionVersion('ast'),
                'phan_helpers' => self::getExtensionVersion('phan_helpers'),
            ],
            'argv' => self::getArgv(),
            'processes' => (int)Config::getValue('processes'),
            'phases' => $phases,
            'files' => [
                'parse' => self::computeFileStats(self::$files[self::FILE_PARSE]),
                'analyze' => self::computeFileStats(self::$files[self::FILE_ANALYZE]),
            ],
            'workers' => $workers,
            'totals' => [
                'wall_s' => \round($wall_s, 6),
                'user_s' => $self_usage['user_s'],
                'sys_s' => $self_usage['sys_s'],
                'children_user_s' => $children_usage['user_s'],
                'children_sys_s' => $children_usage['sys_s'],
                'serial_fraction' => $analyze_wait_s !== null && $wall_s > 0 ? \round(($wall_s - $analyze_wait_s) / $wall_s, 6) : null,
                'imbalance_s' => $worker_count >= 2 ? \round($max_worker_analyze_s - $min_worker_analyze_s, 6) : null,
                'efficiency' => $worker_count > 0 && $analyze_wait_s ? \round($worker_analyze_cpu_s / ($worker_count * $analyze_wait_s), 6) : null,
            ],
            'notes' => $notes,
        ];
    }

    /**
     * Encode the report as JSON. Maps are always encoded as JSON objects.
     *
     * @param array<string,mixed> $report from buildReport()
     * @internal
     */
    public static function encodeJson(array $report): string
    {
        foreach (['parse', 'analyze'] as $kind) {
            if (isset($report['files'][$kind]['by_prefix'])) {
                $report['files'][$kind]['by_prefix'] = (object)$report['files'][$kind]['by_prefix'];
            }
        }
        $report['notes'] = (object)($report['notes'] ?? []);
        if (\is_array($report['opcache'] ?? null)) {
            $report['opcache'] = (object)$report['opcache'];
        }
        // Use the shortest representation of floats that round-trips, regardless of php.ini
        $old_precision = \ini_set('serialize_precision', '-1');
        try {
            $result = \json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PRESERVE_ZERO_FRACTION);
        } finally {
            if (\is_string($old_precision)) {
                \ini_set('serialize_precision', $old_precision);
            }
        }
        return \is_string($result) ? $result : '{}';
    }

    /**
     * Render the report as text, with every line prefixed by "phan-timings".
     *
     * @param array<string,mixed> $report from buildReport()
     * @internal
     */
    public static function formatText(array $report): string
    {
        $p = self::TEXT_PREFIX;
        $row_format = "$p %-22s %9s %9s %9s %9s %9s %12s %6s\n";
        $out = \sprintf($row_format, 'phase', 'wall_s', 'user_s', 'sys_s', 'rss_mb', 'hwm_mb', 'zend_peak_mb', 'count');
        /**
         * @param list<array{name:string,wall_s:float,user_s:float,sys_s:float,rss_mb:float,hwm_mb:float,zend_peak_mb:float,count:int}> $phases
         */
        $format_phases = static function (array $phases, string $indent) use ($row_format): string {
            $result = '';
            foreach ($phases as $phase) {
                $result .= \sprintf(
                    $row_format,
                    $indent . $phase['name'],
                    \sprintf('%.3f', $phase['wall_s']),
                    \sprintf('%.3f', $phase['user_s']),
                    \sprintf('%.3f', $phase['sys_s']),
                    \sprintf('%.1f', $phase['rss_mb']),
                    \sprintf('%.1f', $phase['hwm_mb']),
                    \sprintf('%.1f', $phase['zend_peak_mb']),
                    (string)$phase['count']
                );
            }
            return $result;
        };
        $out .= $format_phases($report['phases'], '');
        $totals = $report['totals'];
        $notes = $report['notes'];
        $out .= \sprintf(
            $row_format,
            'total',
            \sprintf('%.3f', $totals['wall_s']),
            \sprintf('%.3f', $totals['user_s']),
            \sprintf('%.3f', $totals['sys_s']),
            isset($notes['rss_mb']) ? \sprintf('%.1f', $notes['rss_mb']) : 'n/a',
            isset($notes['vmhwm_mb']) ? \sprintf('%.1f', $notes['vmhwm_mb']) : 'n/a',
            \sprintf('%.1f', $notes['zend_peak_mb'] ?? 0),
            ''
        );
        if ($totals['children_user_s'] > 0 || $totals['children_sys_s'] > 0) {
            $out .= \sprintf(
                $row_format,
                'children',
                '',
                \sprintf('%.3f', $totals['children_user_s']),
                \sprintf('%.3f', $totals['children_sys_s']),
                '',
                '',
                '',
                ''
            );
        }
        foreach ($report['workers'] as $worker) {
            $analyze_s = 0.0;
            foreach ($worker['phases'] as $phase) {
                if ($phase['name'] === 'worker_analyze') {
                    $analyze_s += $phase['wall_s'];
                }
            }
            $out .= \sprintf(
                "$p worker %d: analyze_s %.3f files %d private_dirty_mb %s pss_mb %s teardown_gap_s %s\n",
                $worker['id'],
                $analyze_s,
                $worker['files'],
                $worker['private_dirty_mb'] !== null ? \sprintf('%.1f', $worker['private_dirty_mb']) : 'n/a',
                $worker['pss_mb'] !== null ? \sprintf('%.1f', $worker['pss_mb']) : 'n/a',
                $worker['teardown_gap_s'] !== null ? \sprintf('%.3f', $worker['teardown_gap_s']) : 'n/a'
            );
            $out .= $format_phases($worker['phases'], '  ');
        }
        $derived = [];
        foreach (['serial_fraction', 'imbalance_s', 'efficiency'] as $key) {
            if ($totals[$key] !== null) {
                $derived[] = \sprintf('%s %.3f', $key, $totals[$key]);
            }
        }
        if ($derived) {
            $out .= "$p derived " . \implode(' ', $derived) . "\n";
        }
        foreach ($notes as $key => $value) {
            $out .= "$p note $key " . (\is_float($value) ? \sprintf('%.3f', $value) : (string)$value) . "\n";
        }
        foreach ($report['files'] as $kind => $stats) {
            if ($stats['count'] === 0) {
                continue;
            }
            $out .= \sprintf(
                "$p files %s: count %d sum_s %.3f p50_ms %.2f p90_ms %.2f p99_ms %.2f max_ms %.2f\n",
                $kind,
                $stats['count'],
                $stats['sum_s'],
                $stats['p50_ms'],
                $stats['p90_ms'],
                $stats['p99_ms'],
                $stats['max_ms']
            );
            foreach ($stats['by_prefix'] as $prefix => $sum_s) {
                $out .= \sprintf("$p files %s by_prefix %9.3fs %s\n", $kind, $sum_s, $prefix);
            }
            foreach ($stats['top'] as $entry) {
                $out .= \sprintf("$p files %s slowest %9.2fms %s\n", $kind, $entry['ms'], $entry['path']);
            }
        }
        return $out;
    }

    /**
     * @param array<string,int> $end
     */
    private static function closeCurrentPhase(array $end): void
    {
        $name = self::$current_phase;
        $start = self::$current_start;
        if ($name === null || $start === null) {
            return;
        }
        self::$current_phase = null;
        self::$current_start = null;
        $phase = self::$phases[$name] ?? null;
        if ($phase === null) {
            $phase = ['count' => 0];
            foreach (self::DELTA_FIELDS as $field) {
                $phase[$field] = 0;
            }
            foreach (self::MAX_FIELDS as $field) {
                $phase[$field] = 0;
            }
        }
        $phase['count']++;
        foreach (self::DELTA_FIELDS as $field) {
            $phase[$field] += $end[$field] - $start[$field];
        }
        foreach (self::MAX_FIELDS as $field) {
            $phase[$field] = \max($phase[$field], $end[$field]);
        }
        self::$phases[$name] = $phase;
    }

    /**
     * @param array<array-key,mixed> $phases accumulated phase measurements, by phase name
     * @return list<array{name:string,wall_s:float,user_s:float,sys_s:float,rss_mb:float,hwm_mb:float,zend_peak_mb:float,count:int}>
     */
    private static function formatPhases(array $phases): array
    {
        $result = [];
        foreach ($phases as $name => $phase) {
            if (!\is_array($phase)) {
                continue;
            }
            $result[] = [
                'name' => (string)$name,
                'wall_s' => \round((int)($phase['ns'] ?? 0) / 1e9, 6),
                'user_s' => \round((int)($phase['utime_us'] ?? 0) / 1e6, 6),
                'sys_s' => \round((int)($phase['stime_us'] ?? 0) / 1e6, 6),
                'rss_mb' => self::bytesToMb((int)($phase['rss'] ?? 0)),
                'hwm_mb' => self::kbToMb((int)($phase['maxrss_kb'] ?? 0)),
                'zend_peak_mb' => self::bytesToMb((int)($phase['zend_peak'] ?? 0)),
                'count' => (int)($phase['count'] ?? 0),
            ];
        }
        return $result;
    }

    /**
     * @param array<string,int> $ns_by_path
     * @return array{count:int,sum_s:float,p50_ms:float,p90_ms:float,p99_ms:float,max_ms:float,top:list<array{path:string,ms:float}>,by_prefix:array<string,float>}
     */
    private static function computeFileStats(array $ns_by_path): array
    {
        $count = \count($ns_by_path);
        $values = \array_values($ns_by_path);
        \sort($values);
        $percentile = static function (float $q) use ($values, $count): float {
            if ($count === 0) {
                return 0.0;
            }
            // nearest-rank percentile
            return \round($values[\max(0, (int)\ceil($q * $count) - 1)] / 1e6, 3);
        };
        \arsort($ns_by_path);
        $top = [];
        foreach (\array_slice($ns_by_path, 0, self::TOP_FILE_COUNT, true) as $path => $ns) {
            $top[] = ['path' => (string)$path, 'ms' => \round($ns / 1e6, 3)];
        }
        $root = \str_replace('\\', '/', Config::getProjectRootDirectory()) . '/';
        $ns_by_prefix = [];
        foreach ($ns_by_path as $path => $ns) {
            $prefix = self::getFirstPathComponent((string)$path, $root);
            $ns_by_prefix[$prefix] = ($ns_by_prefix[$prefix] ?? 0) + $ns;
        }
        \arsort($ns_by_prefix);
        $by_prefix = [];
        foreach ($ns_by_prefix as $prefix => $ns) {
            $by_prefix[(string)$prefix] = \round($ns / 1e9, 6);
        }
        return [
            'count' => $count,
            'sum_s' => \round(\array_sum($values) / 1e9, 6),
            'p50_ms' => $percentile(0.50),
            'p90_ms' => $percentile(0.90),
            'p99_ms' => $percentile(0.99),
            'max_ms' => $count > 0 ? \round($values[$count - 1] / 1e6, 3) : 0.0,
            'top' => $top,
            'by_prefix' => $by_prefix,
        ];
    }

    /**
     * Returns the first directory of $path relative to the project root, or '.' for files in the project root.
     */
    private static function getFirstPathComponent(string $path, string $root): string
    {
        $path = \str_replace('\\', '/', $path);
        if (\str_starts_with($path, $root)) {
            $path = \substr($path, \strlen($root));
        }
        $path = \ltrim((string)\preg_replace('@^(\./)+@', '', $path), '/');
        $pos = \strpos($path, '/');
        return $pos === false ? '.' : \substr($path, 0, $pos);
    }

    /**
     * @return array<string,int>
     */
    private static function snapshot(): array
    {
        $usage = \getrusage();
        return [
            'ns' => \hrtime(true),
            'utime_us' => ($usage['ru_utime.tv_sec'] ?? 0) * 1000000 + ($usage['ru_utime.tv_usec'] ?? 0),
            'stime_us' => ($usage['ru_stime.tv_sec'] ?? 0) * 1000000 + ($usage['ru_stime.tv_usec'] ?? 0),
            'minflt' => $usage['ru_minflt'] ?? 0,
            'majflt' => $usage['ru_majflt'] ?? 0,
            'nvcsw' => $usage['ru_nvcsw'] ?? 0,
            'nivcsw' => $usage['ru_nivcsw'] ?? 0,
            'rss' => self::readRssBytes(),
            'maxrss_kb' => $usage['ru_maxrss'] ?? 0,
            'zend_peak' => \memory_get_peak_usage(true),
        ];
    }

    /**
     * @return array{user_s:float,sys_s:float,minflt:int,majflt:int,nvcsw:int,nivcsw:int}
     */
    private static function readRusage(int $mode): array
    {
        $usage = \getrusage($mode);
        return [
            'user_s' => \round(($usage['ru_utime.tv_sec'] ?? 0) + ($usage['ru_utime.tv_usec'] ?? 0) / 1e6, 6),
            'sys_s' => \round(($usage['ru_stime.tv_sec'] ?? 0) + ($usage['ru_stime.tv_usec'] ?? 0) / 1e6, 6),
            'minflt' => $usage['ru_minflt'] ?? 0,
            'majflt' => $usage['ru_majflt'] ?? 0,
            'nvcsw' => $usage['ru_nvcsw'] ?? 0,
            'nivcsw' => $usage['ru_nivcsw'] ?? 0,
        ];
    }

    private static function hasProc(): bool
    {
        if (self::$has_proc === null) {
            self::$has_proc = \PHP_OS_FAMILY === 'Linux' && \is_readable('/proc/self/statm');
            if (self::$has_proc && \function_exists('posix_sysconf') && \defined('POSIX_SC_PAGESIZE')) {
                $page_size = \posix_sysconf(\constant('POSIX_SC_PAGESIZE'));
                if ($page_size > 0) {
                    self::$page_size = $page_size;
                }
            }
        }
        return self::$has_proc;
    }

    /**
     * Returns the resident set size of this process from /proc/self/statm, or 0 if unavailable
     */
    private static function readRssBytes(): int
    {
        if (!self::hasProc()) {
            return 0;
        }
        $statm = \file_get_contents('/proc/self/statm');
        if (!\is_string($statm)) {
            return 0;
        }
        $fields = \explode(' ', $statm, 3);
        return (int)($fields[1] ?? 0) * self::$page_size;
    }

    /**
     * Returns Rss, Pss, Private_* and Shared_* from /proc/self/smaps_rollup and VmHWM from /proc/self/status, in kB.
     * Returns an empty array if these are unavailable.
     *
     * @return array<string,int>
     */
    private static function readMemoryKb(): array
    {
        if (!self::hasProc()) {
            return [];
        }
        $result = [];
        if (\is_readable('/proc/self/smaps_rollup')) {
            $smaps = \file_get_contents('/proc/self/smaps_rollup');
            if (\is_string($smaps) && \preg_match_all('/^(\w+):\s+(\d+) kB$/m', $smaps, $matches, \PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (\in_array($match[1], self::SMAPS_FIELDS, true)) {
                        $result[$match[1]] = (int)$match[2];
                    }
                }
            }
        }
        $status = \file_get_contents('/proc/self/status');
        if (\is_string($status) && \preg_match('/^VmHWM:\s+(\d+) kB$/m', $status, $match)) {
            $result['VmHWM'] = (int)$match[1];
        }
        return $result;
    }

    /**
     * @return ?array<string,bool|string>
     */
    private static function getOpcacheSettings(): ?array
    {
        if (!\extension_loaded('Zend OPcache')) {
            return null;
        }
        $result = [];
        foreach (['enable_cli', 'file_cache_only'] as $key) {
            $result[$key] = (bool)\ini_get("opcache.$key");
        }
        foreach (['file_cache', 'jit', 'jit_buffer_size', 'interned_strings_buffer'] as $key) {
            $result[$key] = (string)\ini_get("opcache.$key");
        }
        return $result;
    }

    private static function getExtensionVersion(string $extension): ?string
    {
        if (!\extension_loaded($extension)) {
            return null;
        }
        $version = \phpversion($extension);
        return \is_string($version) ? $version : '';
    }

    /**
     * @return list<string>
     */
    private static function getArgv(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $result = [];
        foreach (\is_array($argv) ? $argv : [] as $arg) {
            $result[] = (string)$arg;
        }
        return $result;
    }

    private static function bytesToMb(int $bytes): float
    {
        return \round($bytes / 1048576, 3);
    }

    private static function kbToMb(int $kb): float
    {
        return \round($kb / 1024, 3);
    }
}
