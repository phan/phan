<?php declare(strict_types = 1);
namespace Phan\Tests;

use Phan\CodeBase;
use Phan\Language\Type;

class MultiFileTest extends AbstractPhanFileTest {

    public function getTestFiles() {
        return [
            // Issue #157
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '157_a.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '157_b.php'
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '157.php' . AbstractPhanFileTest::EXPECTED_SUFFIX
            ],

            // Issue #245
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '245_a.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '245_b.php'
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '245.php' . AbstractPhanFileTest::EXPECTED_SUFFIX
            ],

            // Issue #301
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '301_a.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '301_b.php'
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '301.php' . AbstractPhanFileTest::EXPECTED_SUFFIX
            ],

            // Manually add additional file sets and expected
            // output here.

        ];
    }
}
