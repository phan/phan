<?php

declare(strict_types=1);

namespace Phan\Tests;

use const DIRECTORY_SEPARATOR;

/**
 * Integration tests for strict_array_checking configuration option
 *
 * Tests that the strict_array_checking setting properly controls warnings
 * for potentially invalid array offsets.
 */
class StrictArrayCheckingTest extends AbstractPhanFileTestBase
{
    /**
     * @return list<array{0:list<string>,1:string,2?:string}>
     */
    public static function getTestFiles(): array
    {
        $test_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'strict_array_checking_test';
        $src_file = $test_dir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'test.php';
        $strict_config = $test_dir . DIRECTORY_SEPARATOR . '.phan' . DIRECTORY_SEPARATOR . 'config.php';
        $non_strict_config = $test_dir . DIRECTORY_SEPARATOR . '.phan' . DIRECTORY_SEPARATOR . 'config_non_strict.php';
        $expected_strict = $test_dir . DIRECTORY_SEPARATOR . 'expected' . DIRECTORY_SEPARATOR . 'strict_true.php' . self::EXPECTED_SUFFIX;
        $expected_non_strict = $test_dir . DIRECTORY_SEPARATOR . 'expected' . DIRECTORY_SEPARATOR . 'strict_false.php' . self::EXPECTED_SUFFIX;

        return [
            // Test with strict_array_checking = true
            [
                [$src_file],
                $expected_strict,
                $strict_config,
            ],

            // Test with strict_array_checking = false
            [
                [$src_file],
                $expected_non_strict,
                $non_strict_config,
            ],
        ];
    }
}
