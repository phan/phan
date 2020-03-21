<?php

declare(strict_types=1);

namespace Phan\Tests\Language\Internal;

use Phan\Tests\BaseTest;
use ReflectionExtension;

use function array_key_exists;
use function uksort;

/**
 * This is a sanity check that Phan's property signature map has the correct structure
 * and can be parsed into a property signature.
 */
final class ConstantDocumentationMapTest extends BaseTest
{
    private const EXTENSIONS_TESTED = [
        'ast',
    ];

    public function testConstantsDocumented(): void
    {
        $map = require(\realpath(__DIR__) . '/../../../../src/Phan/Language/Internal/ConstantDocumentationMap.php');
        $failures = '';
        foreach (self::EXTENSIONS_TESTED as $ext) {
            $constants = (new ReflectionExtension($ext))->getConstants();
            uksort($constants, 'strcmp');
            foreach ($constants as $key => $_) {
                if (!array_key_exists($key, $map)) {
                    $failures .= "$ext: Missing documentation for $key\n";
                }
            }
        }
        $this->assertSame('', $failures, 'This test was written for php-ast 1.0.6 and php <=7.4. If you are using a newer php-ast version, this test failure is expected for `ast:`');
    }
}
