<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Context;

/**
 * Plugins can implement this to analyze no-op string statements
 */
interface AnalyzeLiteralStatementCapability
{
    /**
     * Analyze a string literal statement,
     * after parsing and before analyzing.
     *
     * @param CodeBase $code_base
     *
     * @param Context $context
     *
     * @param string $statement
     * The no-op literal statement
     *
     * @return bool
     * Whether the statement was consumed in any way (i.e. it wasn't no-op)
     */
    public function analyzeStringLiteralStatement(
        CodeBase $code_base,
        Context $context,
        string $statement
    ): bool;
}
