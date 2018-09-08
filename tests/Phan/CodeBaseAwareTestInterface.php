<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\CodeBase;

/**
 * This represents a test case that has a CodeBase instance.
 *
 * This is used by PhanTestListener to avoid wasting memory
 * by removing cloned CodeBase instances after the test finishes running.
 */
interface CodeBaseAwareTestInterface
{

    /**
     * @param ?CodeBase $code_base
     * @return void
     */
    public function setCodeBase(CodeBase $code_base = null);
}
