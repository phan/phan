<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\Config;

class PhanTest extends AbstractPhanFileTest
{
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles()
    {

        // Read and apply any custom configuration
        // overrides for the tests.
        $test_config_file_name = dirname(__FILE__) . '/../.phan/config.php';
        foreach (require($test_config_file_name) as $key => $value) {
            Config::setValue($key, $value);
        }

        return $this->scanSourceFilesDir(TEST_FILE_DIR, EXPECTED_DIR);
    }
}
