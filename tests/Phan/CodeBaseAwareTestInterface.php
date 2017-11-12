<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\CodeBase;

interface CodeBaseAwareTestInterface
{

    /** @param ?CodeBase $codeBase */
    public function setCodeBase(CodeBase $codeBase = null);
}
