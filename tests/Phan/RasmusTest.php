<?php declare(strict_types = 1);

namespace Phan\Tests;

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
