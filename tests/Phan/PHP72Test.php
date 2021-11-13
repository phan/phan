<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;

/**
 * Unit tests of Phan analysis targeting PHP 7.2 codebases and function/method signatures.
 */
class PHP72Test extends AbstractPhanFileTest
{
    private const OVERRIDES = [
        'allow_method_param_type_widening' => true,
        'target_php_version' => '7.2',
    ];

    public function setUp(): void
    {
        parent::setUp();
        foreach (self::OVERRIDES as $key => $value) {
            Config::setValue($key, $value);
        }
    }

    /**
     * This reads all files in a test directory (e.g. `tests/files/src`), runs
     * the analyzer on each and compares the output
     * to the files' counterpart in `tests/files/expected`
     *
     * @param non-empty-list<string> $test_file_list
     * @param string $expected_file_path
     * @param ?string $config_file_path
     * @dataProvider getTestFiles
     * @override
     */
    public function testFiles(array $test_file_list, string $expected_file_path, ?string $config_file_path = null): void
    {
        $skip_reason = null;
        $main_path = \basename(\reset($test_file_list));
        if (\PHP_VERSION_ID >= 80000) {
            switch ($main_path) {
                case '0006_deprecated_create_internal_function.php':
                    $skip_reason = 'Skip create_internal_function was removed';
                    break;
            }
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
        return $this->scanSourceFilesDir(\PHP72_TEST_FILE_DIR, \PHP72_EXPECTED_DIR);
    }
}
