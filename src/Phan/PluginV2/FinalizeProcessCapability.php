<?php

declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;

/**
 * Use PluginV2 instead
 */
interface FinalizeProcessCapability
{
    /**
     * This is called after the other forms of analysis are finished running.
     *
     * @return void
     */
    public function finalizeProcess(CodeBase $code_base);
}
