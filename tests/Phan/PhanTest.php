<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\CodeBase;
use Phan\Language\Type;

class PhanTest extends AbstractPhanFileTest
{
    public function getTestFiles()
    {
        return $this->scanSourceFilesDir(TEST_FILE_DIR, EXPECTED_DIR);
    }
}
