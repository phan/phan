<?php

declare(strict_types=1);

namespace Phan;

use Phan\Plugin\ConfigPluginSet;
use Phan\Tests\AbstractPhanFileTestBase;

/**
 * Unit tests of Phan analysis targeting PHP 8.4 codebases with minimum_target_php_version of 8.4.
 */
final class PHP84Test extends AbstractPhanFileTestBase
{
    private const OVERRIDES = [
        'unused_variable_detection' => true,  // for use with tests of arrow functions
        'redundant_condition_detection' => true,  // for use with typed properties
        'dead_code_detection' => true,  // for use with constructor property promotion, etc.
        'target_php_version' => '8.4',
        'minimum_target_php_version' => '8.4',  // Test that checks and type inferences for older php versions aren't supported.
        'plugins' => [
            'DuplicateArrayKeyPlugin',
            'EmptyMethodAndFunctionPlugin',
            'UnknownElementTypePlugin',
            'UnreachableCodePlugin',
            'UseReturnValuePlugin',
            'AsymmetricVisibilityPlugin',
        ],
        'plugin_config' => ['infer_pure_methods' => true],
        // Additional stubs beyond bundled defaults (bundled: soap, tidy, xsl, etc.)
        'autoload_internal_extension_signatures' => [
            'bcmath'    => 'internal/stubs/bcmath.phan_php',
            'intl'      => 'internal/stubs/intl.phan_php',
            'mysqli'    => 'internal/stubs/mysqli.phan_php',
            'pdo_pgsql' => 'internal/stubs/pdo_pgsql.phan_php',
            'pgsql'     => 'internal/stubs/pgsql.phan_php',
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        foreach (self::OVERRIDES as $key => $value) {
            Config::setValue($key, $value);
        }
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * This reads all files in a test directory (e.g. `tests/files/src`), runs
     * the analyzer on each and compares the output
     * to the files' counterpart in `tests/files/expected`
     *
     * @param non-empty-list<string> $test_file_list
     * @param string $expected_file_path
     * @param ?string $config_file_path
     *
     * @dataProvider getTestFiles
     * @override
     */
    public function testFiles(array $test_file_list, string $expected_file_path, ?string $config_file_path = null): void
    {
        $skip_reason = null;
        $main_path = \basename(\reset($test_file_list));
        if (\PHP_VERSION_ID < 80400) {
            $skip_reason = 'Skip PHP 8.4 is required';
        }
        if ($skip_reason !== null) {
            $this->markTestSkipped("Skipping test for $main_path: $skip_reason");
        }
        parent::testFiles($test_file_list, $expected_file_path, $config_file_path);
    }

    /**
     * @suppress PhanUndeclaredConstant
     */
    public static function getTestFiles(): array
    {
        return self::scanSourceFilesDir(\PHP84_TEST_FILE_DIR, \PHP84_EXPECTED_DIR);
    }
}
