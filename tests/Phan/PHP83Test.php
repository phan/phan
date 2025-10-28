<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Plugin\ConfigPluginSet;

/**
 * Unit tests of Phan analysis targeting PHP 8.3 codebases with minimum_target_php_version of 8.3.
 */
final class PHP83Test extends AbstractPhanFileTestBase
{
    private const OVERRIDES = [
        'unused_variable_detection' => true,  // for use with tests of arrow functions
        'redundant_condition_detection' => true,  // for use with typed properties
        'dead_code_detection' => true,  // for use with constructor property promotion, etc.
        'target_php_version' => '8.3',
        'minimum_target_php_version' => '8.3',  // Test that checks and type inferences for older php versions aren't supported.
        'plugins' => [
            'DuplicateArrayKeyPlugin',
            'EmptyMethodAndFunctionPlugin',
            'UnknownElementTypePlugin',
            'UnreachableCodePlugin',
            'UseReturnValuePlugin',
        ],
        'plugin_config' => ['infer_pure_methods' => true],
        'autoload_internal_extension_signatures' => [
            'intl'    => 'internal/stubs/intl.phan_php',
            'ldap'    => 'internal/stubs/ldap.phan_php',
            'pgsql'   => 'internal/stubs/pgsql.phan_php',
            'sockets' => 'internal/stubs/sockets.phan_php',
            'zip'     => 'internal/stubs/zip.phan_php',
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
        if (\PHP_VERSION_ID < 80300) {
            $skip_reason = 'Skip PHP 8.3 is required';
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
        return self::scanSourceFilesDir(\PHP83_TEST_FILE_DIR, \PHP83_EXPECTED_DIR);
    }
}
