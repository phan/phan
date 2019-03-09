<?php declare(strict_types=1);

namespace Phan\Tests;

/**
 * Runs tests/files/src/START_RANGE-END_RANGE
 *
 * The default type of test for Phan
 *
 * Verifies that the analysis of a single file with default settings has the expected output.
 */
abstract class PhanTestRange extends PhanTestCommon
{

    const START_RANGE = '';
    const END_RANGE = '';

    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles()
    {
        return array_filter(
            $this->getAllTestFiles(),
            /**
             * @param array{0:array{0:string},1:string} $data
             */
            static function (array $data) : bool {
                $expected_file = basename($data[1]);
                return strcmp($expected_file, static::START_RANGE) >= 0 && strcmp($expected_file, static::END_RANGE) < 0;
            }
        );
    }
}
