<?php declare(strict_types=1);

namespace Phan\Tests;

/**
 * Runs tests/files/src/0600+
 *
 * The default type of test for Phan
 *
 * Verifies that the analysis of a single file with default settings has the expected output.
 */
class PhanTestNew extends PhanTestCommon
{
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles() : array
    {
        return \array_filter(
            $this->getAllTestFiles(),
            /**
             * @param array{0:array{0:string},1:string} $data
             */
            static function (array $data) : bool {
                $expected_file = \basename($data[1]);
                // Run everything except 0000-0599 (including tests starting with punctuation/letters if needed)
                return !(\strcmp($expected_file, '0000') >= 0 && \strcmp($expected_file, '0600') < 0);
            }
        );
    }
}
