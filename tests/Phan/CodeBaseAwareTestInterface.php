<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\CodeBase;

interface CodeBaseAwareTestInterface
{

    /** @param ?CodeBase $code_base */
    public function setCodeBase(CodeBase $code_base = null);
}
