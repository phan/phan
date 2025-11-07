<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Analysis;
use Phan\CodeBase;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Phan\Analysis
 */
final class InternalStubCacheTest extends TestCase
{
    public function testInternalStubCacheHits(): void
    {
        Analysis::clearInternalStubAstCache();
        $stub_file = \tempnam(\sys_get_temp_dir(), 'phan_stub_cache_');
        if ($stub_file === false) {
            $this->fail('Failed to create a temporary stub file for cache test');
        }
        \file_put_contents($stub_file, "<?php function stub_cache_example() {}");

        try {
            $first_code_base = new CodeBase([], [], [], [], []);
            Analysis::parseFile($first_code_base, $stub_file, false, null, true);
            $stats = Analysis::getInternalStubAstCacheStats();
            $this->assertSame(['hits' => 0, 'misses' => 1], $stats, 'First parse should record one miss and zero hits');

            $second_code_base = new CodeBase([], [], [], [], []);
            Analysis::parseFile($second_code_base, $stub_file, false, null, true);
            $stats = Analysis::getInternalStubAstCacheStats();
            $this->assertSame(['hits' => 1, 'misses' => 1], $stats, 'Second parse should reuse cached AST');
        } finally {
            @\unlink($stub_file);
            Analysis::clearInternalStubAstCache();
        }
    }
}
