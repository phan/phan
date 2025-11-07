<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use PHPUnit\Framework\TestCase;

/**
 * Ensures the internal stub cache avoids reparsing identical files.
 *
 * @covers \Phan\Analysis
 */
final class InternalStubCacheTest extends TestCase
{
    /**
     * @throws \Phan\Exception\FQSENException
     */
    public function testInternalStubCacheHits(): void
    {
        Analysis::clearInternalStubCache();
        $stub_file = \tempnam(\sys_get_temp_dir(), 'phan_stub_cache_');
        if ($stub_file === false) {
            $this->fail('Failed to create a temporary stub file for cache test');
        }
        \file_put_contents($stub_file, "<?php function stub_cache_example() {}");

        try {
            $first_code_base = new CodeBase([], [], [], [], []);
            Analysis::parseFile($first_code_base, $stub_file, false, null, true);
            $stats = Analysis::getInternalStubCacheStats();
            $this->assertSame(['hits' => 0, 'misses' => 1], $stats, 'First parse should record one miss and zero hits');
            $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString('\\stub_cache_example');
            $this->assertTrue($first_code_base->hasFunctionWithFQSEN($function_fqsen));

            $second_code_base = new CodeBase([], [], [], [], []);
            Analysis::parseFile($second_code_base, $stub_file, false, null, true);
            $stats = Analysis::getInternalStubCacheStats();
            $this->assertSame(['hits' => 1, 'misses' => 1], $stats, 'Second parse should reuse cached AST');
            $this->assertTrue($second_code_base->hasFunctionWithFQSEN($function_fqsen));
        } finally {
            @\unlink($stub_file);
            Analysis::clearInternalStubCache();
        }
    }
}
