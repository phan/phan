<?php

declare(strict_types=1);

namespace Phan\Tests\Library;

use Phan\ForkPool;
use Phan\Library\PhaseTimer;
use Phan\Tests\TestBase;

/**
 * Unit tests of PhaseTimer (--dump-phase-timings and --phase-timings-json)
 *
 * @phan-file-suppress PhanAccessMethodInternal
 */
final class PhaseTimerTest extends TestBase
{
    protected function tearDown(): void
    {
        PhaseTimer::resetForTests();
        parent::tearDown();
    }

    public function testDisabledIsNoOp(): void
    {
        PhaseTimer::resetForTests();
        PhaseTimer::enableFromCliOpts(['processes' => '2']);
        $this->assertFalse(PhaseTimer::$enabled);

        PhaseTimer::begin('parse');
        PhaseTimer::recordFile(PhaseTimer::FILE_PARSE, 'src/a.php', 1000);
        PhaseTimer::note('parsed_files', 1);
        PhaseTimer::beginWorker(0, 1);
        $this->assertSame([], PhaseTimer::finishWorker());
        PhaseTimer::recordWorker(0, ['id' => 0, 'phases' => []]);
        PhaseTimer::finish();

        $this->assertArrayNotHasKey('__phase', $GLOBALS);
        $report = PhaseTimer::buildReport();
        $this->assertSame([], $report['phases']);
        $this->assertSame([], $report['workers']);
        $this->assertSame(0, $report['files']['parse']['count']);
        $this->assertArrayNotHasKey('parsed_files', $report['notes']);
    }

    public function testPhasesAccumulateInOrder(): void
    {
        PhaseTimer::enableFromCliOpts(['dump-phase-timings' => false]);
        $this->assertTrue(PhaseTimer::$enabled);

        PhaseTimer::begin('a');
        $this->assertSame('a', $GLOBALS['__phase']);
        PhaseTimer::begin('b');
        PhaseTimer::begin('a');
        PhaseTimer::begin('c');
        $this->assertSame('c', $GLOBALS['__phase']);
        PhaseTimer::recordFile(PhaseTimer::FILE_PARSE, 'src/a.php', 2000000);
        PhaseTimer::recordFile(PhaseTimer::FILE_PARSE, 'src/a.php', 1000000);
        PhaseTimer::recordFile(PhaseTimer::FILE_PARSE, 'vendor/b.php', 5000000);
        PhaseTimer::note('parsed_files', 2);

        $report = PhaseTimer::buildReport();
        // 'c' is still open, so only the closed phases are reported
        $this->assertSame(['a', 'b'], \array_column($report['phases'], 'name'));
        $this->assertSame([2, 1], \array_column($report['phases'], 'count'));
        foreach ($report['phases'] as $phase) {
            $this->assertSame(['name', 'wall_s', 'user_s', 'sys_s', 'rss_mb', 'hwm_mb', 'zend_peak_mb', 'count'], \array_keys($phase));
            $this->assertIsFloat($phase['wall_s']);
            $this->assertGreaterThanOrEqual(0.0, $phase['wall_s']);
        }
        $parse = $report['files']['parse'];
        $this->assertSame(2, $parse['count']);
        $this->assertSame(0.008, $parse['sum_s']);
        $this->assertSame(5.0, $parse['max_ms']);
        $this->assertSame(3.0, $parse['p50_ms']);
        $this->assertSame([['path' => 'vendor/b.php', 'ms' => 5.0], ['path' => 'src/a.php', 'ms' => 3.0]], $parse['top']);
        $this->assertSame(['vendor' => 0.005, 'src' => 0.003], $parse['by_prefix']);
        $this->assertSame(2, $report['notes']['parsed_files']);

        $text = PhaseTimer::formatText($report);
        foreach (\explode("\n", \rtrim($text, "\n")) as $line) {
            $this->assertStringStartsWith('phan-timings ', $line);
        }
        $this->assertMatchesRegularExpression('/^phan-timings a +[0-9.]+ .* 2$/m', $text);
        $this->assertStringContainsString('phan-timings files parse slowest      5.00ms vendor/b.php', $text);
    }

    public function testWorkerPayloadMerge(): void
    {
        // Simulate the worker side (normally run in a forked child)
        PhaseTimer::enableFromCliOpts(['dump-phase-timings' => false]);
        PhaseTimer::begin('fork');
        PhaseTimer::beginWorker(1, 3);
        PhaseTimer::begin('worker_analyze');
        PhaseTimer::recordFile(PhaseTimer::FILE_ANALYZE, 'src/x.php', 4000000);
        PhaseTimer::note('worker_note', 'value');
        PhaseTimer::begin('worker_emit');
        $payload = PhaseTimer::finishWorker();
        $this->assertSame(1, $payload['id']);
        $this->assertSame(3, $payload['task_count']);
        $this->assertSame(['worker_analyze', 'worker_emit'], \array_keys($payload['phases']));
        $this->assertSame(['src/x.php' => 4000000], $payload['files']);
        $this->assertIsInt($payload['end_ns']);
        // The inherited parent phase ('fork') was discarded by beginWorker()
        $this->assertArrayNotHasKey('fork', $payload['phases']);

        // Simulate the parent side
        PhaseTimer::resetForTests();
        PhaseTimer::enableFromCliOpts(['dump-phase-timings' => false]);
        PhaseTimer::begin('analyze_wait');
        PhaseTimer::recordFile(PhaseTimer::FILE_ANALYZE, 'src/y.php', 1000000);
        PhaseTimer::recordWorker(1, \unserialize(\serialize($payload), ['allowed_classes' => false]));
        PhaseTimer::recordWorkerExit(1, (int)$payload['end_ns'] + 250000000);
        \usleep(1000);
        PhaseTimer::begin('collect_results');

        $report = PhaseTimer::buildReport();
        $this->assertCount(1, $report['workers']);
        $worker = $report['workers'][0];
        $this->assertSame(['id', 'files', 'phases', 'private_dirty_mb', 'pss_mb', 'teardown_gap_s'], \array_keys($worker));
        $this->assertSame(1, $worker['id']);
        $this->assertSame(3, $worker['files']);
        $this->assertSame(['worker_analyze', 'worker_emit'], \array_column($worker['phases'], 'name'));
        $this->assertSame(0.25, $worker['teardown_gap_s']);
        $this->assertSame(2, $report['files']['analyze']['count']);
        $this->assertSame('src/x.php', $report['files']['analyze']['top'][0]['path']);
        $this->assertSame('value', $report['notes']['worker1_worker_note']);
        $this->assertSame(1, $report['notes']['worker_count']);
        $this->assertNotNull($report['totals']['serial_fraction']);
        $this->assertNotNull($report['totals']['efficiency']);
        // Only one worker, so there is no imbalance to report
        $this->assertNull($report['totals']['imbalance_s']);
    }

    /**
     * @requires extension pcntl
     */
    public function testForkPoolSendsWorkerTimings(): void
    {
        if (\extension_loaded('grpc')) {
            $this->markTestIncomplete('Ignoring because grpc PHP extension is enabled');
        }
        PhaseTimer::enableFromCliOpts(['dump-phase-timings' => false]);
        PhaseTimer::begin('fork');
        $pool = new ForkPool(
            [['a.php', 'b.php'], ['c.php']],
            static function (): void {
            },
            static function (int $unused_i, string $file, int $unused_count): void {
                PhaseTimer::recordFile(PhaseTimer::FILE_ANALYZE, $file, 1000);
            },
            /**
             * @return list<never> no issues
             */
            static function (): array {
                return [];
            }
        );
        PhaseTimer::begin('analyze_wait');
        $this->assertSame([], $pool->wait());
        PhaseTimer::begin('collect_results');

        $report = PhaseTimer::buildReport();
        $this->assertSame([0, 1], \array_column($report['workers'], 'id'));
        $this->assertSame([2, 1], \array_column($report['workers'], 'files'));
        foreach ($report['workers'] as $worker) {
            $this->assertSame(['worker_analyze', 'worker_finalize', 'worker_emit'], \array_column($worker['phases'], 'name'));
            $this->assertIsFloat($worker['teardown_gap_s']);
            $this->assertGreaterThanOrEqual(0.0, $worker['teardown_gap_s']);
        }
        $this->assertSame(3, $report['files']['analyze']['count']);
        $this->assertIsFloat($report['totals']['imbalance_s']);
    }

    public function testJsonReport(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'phan-phase-timings');
        $this->assertIsString($path);
        try {
            // When the flag is repeated, the last path is used
            PhaseTimer::enableFromCliOpts(['phase-timings-json' => ['unused.json', $path]]);
            $this->assertTrue(PhaseTimer::$enabled);
            PhaseTimer::begin('parse');
            PhaseTimer::recordFile(PhaseTimer::FILE_PARSE, 'src/a.php', 1000000);
            PhaseTimer::begin('display');
            PhaseTimer::note('issues', 0);
            PhaseTimer::finish();
            $this->assertFalse(PhaseTimer::$enabled);

            $contents = \file_get_contents($path);
            $this->assertIsString($contents);
            $json = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
            $this->assertIsArray($json);
            $this->assertSame(
                ['schema', 'phan_version', 'php_version', 'opcache', 'extensions', 'argv', 'processes', 'phases', 'files', 'workers', 'totals', 'notes'],
                \array_keys($json)
            );
            $this->assertSame(PhaseTimer::SCHEMA, $json['schema']);
            $this->assertSame(['ast', 'phan_helpers'], \array_keys($json['extensions']));
            $this->assertSame(['parse', 'display'], \array_column($json['phases'], 'name'));
            $this->assertSame(['parse', 'analyze'], \array_keys($json['files']));
            $this->assertSame(
                ['count', 'sum_s', 'p50_ms', 'p90_ms', 'p99_ms', 'max_ms', 'top', 'by_prefix'],
                \array_keys($json['files']['parse'])
            );
            $this->assertSame(
                ['wall_s', 'user_s', 'sys_s', 'children_user_s', 'children_sys_s', 'serial_fraction', 'imbalance_s', 'efficiency'],
                \array_keys($json['totals'])
            );
            $this->assertSame(0, $json['notes']['issues']);
            // Maps are objects even when empty
            $this->assertStringContainsString('"by_prefix": {}', $contents);
            $this->assertIsFloat($json['totals']['wall_s']);
        } finally {
            @\unlink($path);
        }
    }
}
