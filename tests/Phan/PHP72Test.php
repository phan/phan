<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\Config;

class PHP72Test extends AbstractPhanFileTest
{
    const OVERRIDES = [
        'allow_method_param_type_widening' => true,
        'target_php_version' => '7.2',
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
     * to the files's counterpart in `tests/files/expected`
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
        $main_path = basename(reset($test_file_list));
        if (PHP_VERSION_ID < 70200) {
            switch ($main_path) {
                case '0002_hash.php':
                    $skip_reason = 'Skip HashContext has no stub';
                    break;
                case '0003_is_iterable.php':
                    $skip_reason = 'Skip isIterateable not added in php < 7.2, no stub exists';
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
    public function getTestFiles()
    {
        return $this->scanSourceFilesDir(PHP72_TEST_FILE_DIR, PHP72_EXPECTED_DIR);
    }
}
