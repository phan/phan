<?php declare(strict_types = 1);

namespace Phan\Tests;


class PHP72Test extends AbstractPhanFileTest {
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles() {
        return $this->scanSourceFilesDir(PHP72_TEST_FILE_DIR, PHP72_EXPECTED_DIR);
    }
}
