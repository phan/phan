<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;

/**
 * Unit tests of Phan analysis targeting PHP 7.4 codebases.
 * PHP 7.4 will be out in late 2019
 */
final class PHP74Test extends AbstractPhanFileTest
{
    const OVERRIDES = [
        'allow_method_param_type_widening' => true,
        'target_php_version' => '7.4',
    ];

    public function setUp()
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
     * @param string[] $test_file_list
     * @param string $expected_file_path
     * @param ?string $config_file_path
     *
     * @dataProvider getTestFiles
     * @override
     */
    public function testFiles($test_file_list, $expected_file_path, $config_file_path = null)
    {
        $skip_reason = null;
        // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
        $main_path = basename(reset($test_file_list));
        if (PHP_VERSION_ID < 70400) {
            $skip_reason = 'Skip PHP 7.4 is required';
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
    public function getTestFiles()
    {
        return $this->scanSourceFilesDir(PHP74_TEST_FILE_DIR, PHP74_EXPECTED_DIR);
    }
}
