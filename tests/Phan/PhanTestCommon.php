<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Plugin\ConfigPluginSet;

/**
 * The default type of test for Phan
 *
 * Verifies that the analysis of a single file with default settings has the expected output.
 */
abstract class PhanTestCommon extends AbstractPhanFileTest
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // Read and apply any custom configuration
        // overrides for the tests.
        $test_config_file_name = \dirname(__FILE__) . '/../.phan_for_test/config.php';
        foreach (require($test_config_file_name) as $key => $value) {
            Config::setValue($key, $value);
        }
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * Get all test files in tests/files/src
     *
     * @suppress PhanUndeclaredConstant
     * @return array<string,array{0:array{0:string},1:string}>
     */
    final public function getAllTestFiles()
    {
        static $results = null;
        return $results ?? $results = $this->scanSourceFilesDir(TEST_FILE_DIR, EXPECTED_DIR);
    }
}
