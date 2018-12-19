<?php declare(strict_types=1);

namespace Phan\Tests;

/**
 * Tests written by rasmus, in RASMUS_TEST_FILE_DIR
 */
class RasmusTest extends AbstractPhanFileTest
{
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles()
    {
        return $this->scanSourceFilesDir(RASMUS_TEST_FILE_DIR, RASMUS_EXPECTED_DIR);
    }
}
