<?php declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;

/**
 * Use PluginV3 instead.
 */
interface BeforeAnalyzeCapability
{
    /**
     * This method is called before analyzing a project and before analyzing methods.
     *
     * @return void
     */
    public function beforeAnalyze(
        CodeBase $code_base
    );
}
