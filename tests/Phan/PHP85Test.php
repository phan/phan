<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Plugin\ConfigPluginSet;

/**
 * Unit tests of Phan analysis targeting PHP 8.5 codebases with minimum_target_php_version of 8.5.
 */
final class PHP85Test extends AbstractPhanFileTestBase
{
    private const OVERRIDES = [
        'unused_variable_detection' => true,
        'redundant_condition_detection' => true,
        'dead_code_detection' => true,
        'target_php_version' => '8.5',
        'minimum_target_php_version' => '8.5',
        'plugins' => [
            'AsymmetricVisibilityPlugin',
            'DuplicateArrayKeyPlugin',
            'EmptyMethodAndFunctionPlugin',
            'UnknownElementTypePlugin',
            'UnreachableCodePlugin',
            'UseReturnValuePlugin',
        ],
        'plugin_config' => ['infer_pure_methods' => true],
        // Additional stubs beyond bundled defaults (bundled: soap, tidy, xsl, etc.)
        'autoload_internal_extension_signatures' => [
            'intl'      => 'internal/stubs/intl.phan_php',
            'pdo_pgsql' => 'internal/stubs/pdo_pgsql.phan_php',
            'pgsql'     => 'internal/stubs/pgsql.phan_php',
            'uri'       => 'internal/stubs/url.phan_php',
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
        if (\PHP_VERSION_ID < 80500) {
            $skip_reason = 'Skip PHP 8.5 is required';
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
        return self::scanSourceFilesDir(\PHP85_TEST_FILE_DIR, \PHP85_EXPECTED_DIR);
    }
}
