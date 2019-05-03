<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\PluginV2;
use ReflectionClass;

/**
 * Unit tests of PluginV2's documentation
 *
 * @phan-file-suppress PhanAccessMethodInternal
 */
final class PluginV2Test extends BaseTest
{
    public function testDocumentation()
    {
        $comment = (new ReflectionClass(PluginV2::class))->getDocComment();
        $this->assertIsString($comment);

        $capabilities = [];
        foreach (\scandir(\dirname(__DIR__, 2) . '/src/Phan/PluginV2') as $file) {
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
        $this->assertSame([], $missing_capabilities, 'should document all PluginV2 capabilities');
    }
}
