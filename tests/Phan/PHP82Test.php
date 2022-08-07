<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Plugin\ConfigPluginSet;

/**
 * Unit tests of Phan analysis targeting PHP 8.2 codebases with minimum_target_php_version of 8.2.
 * PHP 8.2 will be out in 2022.
 */
final class PHP82Test extends AbstractPhanFileTest
{
    private const OVERRIDES = [
        'allow_method_param_type_widening' => true,
        'unused_variable_detection' => true,  // for use with tests of arrow functions
        'redundant_condition_detection' => true,  // for use with typed properties
        'dead_code_detection' => true,  // for use with constructor property promotion, etc.
        'target_php_version' => '8.2',
        'minimum_target_php_version' => '8.2',  // Test that checks and type inferences for older php versions aren't supported.
        'plugins' => [
            'DuplicateArrayKeyPlugin',
            'EmptyMethodAndFunctionPlugin',
            'UnknownElementTypePlugin',
            'UnreachableCodePlugin',
            'UseReturnValuePlugin',
        ],
        'plugin_config' => ['infer_pure_methods' => true],
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
        if (\PHP_VERSION_ID < 80200) {
            $skip_reason = 'Skip PHP 8.2 is required';
        }
        if ($skip_reason !== null) {
            $this->markTestSkipped("Skipping test for $main_path: $skip_reason");
            return;
        }
        parent::testFiles($test_file_list, $expected_file_path, $config_file_path);
    }

    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles(): array
    {
        return $this->scanSourceFilesDir(\PHP82_TEST_FILE_DIR, \PHP82_EXPECTED_DIR);
    }
}
