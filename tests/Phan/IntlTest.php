<?php declare(strict_types = 1);
namespace Phan\Tests;

/**
 * @requires extension intl
 */
class IntlTest extends AbstractPhanFileTest
{

    /**
     * This reads all files in `tests/files/src`, runs
     * the analyzer on each and compares the output
     * to the files's counterpart in
     * `tests/files/expected`
     *
     * @param string[] $test_file_list
     * @param string $expected_file_path
     *
     * @dataProvider getTestFiles
     */
    public function testFiles($test_file_list, $expected_file_path, $config_file_path = null)
    {
        parent::testFiles($test_file_list, $expected_file_path, $config_file_path);
    }

    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles()
    {
        return $this->scanSourceFilesDir(INTL_TEST_FILE_DIR, INTL_EXPECTED_DIR);
    }
}
