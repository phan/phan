<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\PluginV3;
use ReflectionClass;

/**
 * Unit tests of PluginV3's documentation
 */
final class PluginV3Test extends BaseTest
{
    public function testDocumentation(): void
    {
        $comment = (new ReflectionClass(PluginV3::class))->getDocComment();
        $this->assertIsString($comment);

        $capabilities = [];
        foreach (\scandir(\dirname(__DIR__, 2) . '/src/Phan/PluginV3') as $file) {
            if (!\preg_match('/^(\w+Capability)\.php$/', $file, $matches)) {
                continue;
            }
            $capabilities[] = $matches[1];
        }
        $this->assertNotEmpty($capabilities);
        $missing_capabilities = [];
        foreach ($capabilities as $capability) {
            if (\strpos($comment, $capability) === false) {
                $missing_capabilities[] = $capability;
            }
        }
        $this->assertSame([], $missing_capabilities, 'should document all PluginV3 capabilities');
    }
}
