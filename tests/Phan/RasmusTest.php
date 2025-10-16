<?php

declare(strict_types=1);

namespace Phan\Tests;

/**
 * Tests written by rasmus, in RASMUS_TEST_FILE_DIR
 */
class RasmusTest extends AbstractPhanFileTestBase
{
    /**
     * @suppress PhanUndeclaredConstant
     */
    public static function getTestFiles(): array
    {
        return self::scanSourceFilesDir(\RASMUS_TEST_FILE_DIR, \RASMUS_EXPECTED_DIR);
    }
}
