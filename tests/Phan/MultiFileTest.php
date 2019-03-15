<?php declare(strict_types=1);

namespace Phan\Tests;

use const DIRECTORY_SEPARATOR;
use const MULTI_EXPECTED_DIR;
use const MULTI_FILE_DIR;

/**
 * Integration tests that require more than 1 files in a codebase to reproduce
 * (e.g. regression tests for bugs, tests of expected functionality for multiple files, etc)
 *
 * @see self::getTestFiles() for how file groups are represented to test.
 */
class MultiFileTest extends AbstractPhanFileTest
{

    /**
     * @suppress PhanUndeclaredConstant
     * @suppress PhanParamSignatureMismatch
     *
     * The constant MULTI_FILE_DIR is defined in `phpunit.xml`.
     * @return array<int,array{0:array<int,string>,1:string}>
     */
    public function getTestFiles()
    {
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

            // Issue #321
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '321_a.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '321_b.php'
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '321.php' . AbstractPhanFileTest::EXPECTED_SUFFIX,
                MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '321_config.php',
            ],

            // Issue #551
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '551_b.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '551_c.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '551_a.php',
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '551.php' . AbstractPhanFileTest::EXPECTED_SUFFIX
            ],

            // #699
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '699.php',
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '699.php' . AbstractPhanFileTest::EXPECTED_SUFFIX,
                MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '699_config.php',
            ],

            // #704
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '704.php',
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '704.php' . AbstractPhanFileTest::EXPECTED_SUFFIX,
                MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '704_config.php',
            ],

            // #1898
            [
                [
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '1898_a.php',
                    MULTI_FILE_DIR . DIRECTORY_SEPARATOR . '1898_b.php',
                ],
                MULTI_EXPECTED_DIR . DIRECTORY_SEPARATOR . '1898.php' . AbstractPhanFileTest::EXPECTED_SUFFIX,
            ],
            // Manually add additional file sets and expected
            // output here.

        ];
    }
}
