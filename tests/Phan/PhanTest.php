<?php declare(strict_types = 1);

namespace Phan\Tests;


class PhanTest extends AbstractPhanFileTest {
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles() {
        return $this->scanSourceFilesDir(TEST_FILE_DIR, EXPECTED_DIR);
    }
}
